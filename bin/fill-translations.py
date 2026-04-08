#!/usr/bin/env python3
"""
Translation Filler Tool
Auto-fills missing translations or creates translation templates.

Usage:
    python3 bin/fill-translations.py --show-all     # Show all missing with details
    python3 bin/fill-translations.py --create-template  # Create HTML template for translators
"""

import json
import os
import sys
from pathlib import Path
from dataclasses import dataclass
from typing import Dict, List
import argparse
import html
import re
import struct
import io


# Define classes needed from manage_translations
class TranslationEntry:
    """Represents a single translation entry"""
    def __init__(self):
        self.msgid = ""
        self.msgstr = ""
        self.msgid_plural = None
        self.msgstr_plural = []
        self.comment = ""
        self.locations = []
        self.is_fuzzy = False
        self.is_plural = False


class GettextParser:
    """Parse and write gettext PO/POT files"""
    
    def __init__(self, filepath: str):
        self.filepath = filepath
        self.entries: Dict[str, TranslationEntry] = {}
        self.header: TranslationEntry = None
    
    def parse(self) -> None:
        """Parse the gettext file"""
        if not os.path.exists(self.filepath):
            raise FileNotFoundError(f"File not found: {self.filepath}")
        
        with open(self.filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Split by blank lines to get entries
        entries_text = re.split(r'\n\s*\n', content)
        
        for entry_text in entries_text:
            if not entry_text.strip():
                continue
            
            entry = self._parse_entry(entry_text)
            if entry:
                if not entry.msgid and entry.msgstr:  # Header
                    self.header = entry
                else:
                    self.entries[entry.msgid] = entry
    
    def _parse_entry(self, text: str):
        """Parse a single entry block"""
        lines = text.split('\n')
        entry = TranslationEntry()
        
        current_key = None
        current_value = ""
        
        for line in lines:
            # Handle comments
            if line.strip().startswith('#'):
                entry.comment += line + "\n"
                if ', fuzzy' in line:
                    entry.is_fuzzy = True
                continue
            
            line_stripped = line.strip()
            if not line_stripped:
                continue
            
            # Parse msgid
            if line_stripped.startswith('msgid '):
                if current_key == 'msgid' and current_value:
                    entry.msgid = self._unquote_string(current_value)
                current_key = 'msgid'
                current_value = line_stripped[6:].strip()
            
            # Parse msgstr
            elif line_stripped.startswith('msgstr '):
                if current_key == 'msgid' and current_value:
                    entry.msgid = self._unquote_string(current_value)
                current_key = 'msgstr'
                current_value = line_stripped[7:].strip()
            
            # Parse msgid_plural
            elif line_stripped.startswith('msgid_plural '):
                entry.msgid_plural = self._unquote_string(line_stripped[13:].strip())
                entry.is_plural = True
                current_key = 'msgid_plural'
                current_value = ""
            
            # Parse msgstr plurals
            elif re.match(r'msgstr\[\d+\]', line_stripped):
                match = re.match(r'msgstr\[(\d+)\]\s+(.*)', line_stripped)
                if match:
                    index = int(match.group(1))
                    value = self._unquote_string(match.group(2).strip())
                    while len(entry.msgstr_plural) <= index:
                        entry.msgstr_plural.append("")
                    entry.msgstr_plural[index] = value
                current_key = 'msgstr_plural'
            
            # Continuation of previous value (quoted string)
            elif line_stripped.startswith('"') and line_stripped.endswith('"'):
                current_value += "\n" + line_stripped
        
        # Handle final value
        if current_key == 'msgid' and current_value:
            entry.msgid = self._unquote_string(current_value)
        elif current_key == 'msgstr' and current_value:
            entry.msgstr = self._unquote_string(current_value)
        
        if entry.msgid or entry.msgstr:
            return entry
        
        return None
    
    @staticmethod
    def _unquote_string(text: str) -> str:
        """Unquote and unescape gettext strings"""
        text = text.strip()
        
        # Handle multi-line strings
        parts = []
        for line in text.split('\n'):
            line = line.strip()
            if line.startswith('"') and line.endswith('"'):
                line = line[1:-1]
            parts.append(line)
        
        result = "".join(parts)
        # Unescape common sequences
        result = result.replace('\\n', '\n')
        result = result.replace('\\t', '\t')
        result = result.replace('\\\\', '\\')
        result = result.replace('\\"', '"')
        
        return result
    
    def get_missing_translations(self) -> List:
        """Get list of untranslated entries"""
        missing = []
        for msgid, entry in self.entries.items():
            if not msgid:  # Skip empty msgid
                continue
            if not entry.msgstr or not entry.msgstr.strip():
                missing.append((msgid, entry))
        return missing
    
    def get_statistics(self) -> Dict:
        """Get translation statistics"""
        missing = self.get_missing_translations()
        total = len(self.entries)
        translated = total - len(missing)
        
        return {
            'total': total,
            'translated': translated,
            'untranslated': len(missing),
            'percentage': round((translated / total * 100) if total > 0 else 0, 2)
        }


class TranslationManager:
    """Main translation management class"""
    
    def __init__(self, plugin_dir: str, locale: str = 'fr_FR'):
        self.plugin_dir = plugin_dir
        self.lang_dir = os.path.join(plugin_dir, 'lang')
        self.locale = locale
        self.domain = 'my-flying-box'
        
        self.pot_file = os.path.join(self.lang_dir, f'{self.domain}.pot')
        self.po_file = os.path.join(self.lang_dir, f'{self.domain}-{locale}.po')
        self.mo_file = os.path.join(self.lang_dir, f'{self.domain}-{locale}.mo')


def show_detailed_missing(manager: TranslationManager) -> None:
    """Show all missing translations with context"""
    parser = GettextParser(manager.po_file)
    parser.parse()
    
    missing = parser.get_missing_translations()
    
    print("\n" + "=" * 80)
    print(f"DETAILED MISSING TRANSLATIONS ({len(missing)})")
    print("=" * 80 + "\n")
    
    for i, (msgid, entry) in enumerate(missing, 1):
        print(f"{i}. Message ID:")
        print(f"   {msgid}")
        if entry.comment:
            print(f"   Location: {entry.comment.strip()}")
        print()


def create_translator_template(manager: TranslationManager, output_file: str = None) -> None:
    """Create HTML template for translators to fill in missing translations"""
    if output_file is None:
        output_file = os.path.join(
            manager.lang_dir, 
            f'translation-template-{manager.locale}.html'
        )
    
    parser = GettextParser(manager.po_file)
    parser.parse()
    
    missing = parser.get_missing_translations()
    
    html_content = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Translation Template - {manager.locale}</title>
    <style>
        body {{
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }}
        .header {{
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }}
        .header h1 {{
            margin: 0;
            font-size: 28px;
        }}
        .header p {{
            margin: 10px 0 0 0;
            opacity: 0.9;
        }}
        .stats {{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }}
        .stat-card {{
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }}
        .stat-card .value {{
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }}
        .stat-card .label {{
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-top: 8px;
        }}
        .translation-item {{
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 4px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }}
        .translation-item:hover {{
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-left-color: #667eea;
        }}
        .translation-item.filled {{
            border-left-color: #48bb78;
            background: #f0fdf4;
        }}
        .number {{
            display: inline-block;
            background: #667eea;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            text-align: center;
            line-height: 32px;
            font-weight: bold;
            margin-right: 10px;
            flex-shrink: 0;
        }}
        .msgid-label {{
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-top: 10px;
            margin-bottom: 5px;
        }}
        .msgid {{
            font-family: 'Monaco', 'Courier New', monospace;
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            border-left: 2px solid #e0e0e0;
            overflow-x: auto;
            word-break: break-word;
            font-size: 13px;
            line-height: 1.5;
        }}
        .msgstr-label {{
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-top: 10px;
            margin-bottom: 5px;
        }}
        .msgstr {{
            font-family: 'Monaco', 'Courier New', monospace;
            background: #fafafa;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            line-height: 1.5;
            width: 100%;
            box-sizing: border-box;
            min-height: 40px;
        }}
        .context {{
            font-size: 12px;
            color: #666;
            margin-top: 10px;
            padding: 8px;
            background: #f9f9f9;
            border-left: 2px solid #ddd;
        }}
        .context strong {{
            color: #333;
        }}
        .progress {{
            background: #e0e0e0;
            height: 8px;
            border-radius: 4px;
            margin-bottom: 20px;
            overflow: hidden;
        }}
        .progress-bar {{
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            transition: width 0.3s ease;
        }}
        footer {{
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: #999;
            border-top: 1px solid #eee;
        }}
    </style>
</head>
<body>
    <div class="header">
        <h1>📝 Translation Template</h1>
        <p>Plugin: My Flying Box | Locale: {manager.locale}</p>
    </div>
    
    <div class="stats">
        <div class="stat-card">
            <div class="value">{len(missing)}</div>
            <div class="label">Missing Translations</div>
        </div>
        <div class="stat-card">
            <div class="value">{parser.get_statistics()['percentage']}%</div>
            <div class="label">Completion Rate</div>
        </div>
        <div class="stat-card">
            <div class="value">{parser.get_statistics()['translated']}</div>
            <div class="label">Already Translated</div>
        </div>
    </div>
    
    <div class="progress">
        <div class="progress-bar" style="width: {parser.get_statistics()['percentage']}%"></div>
    </div>
    
    <h2>Missing Translations to Complete</h2>
    <p>Below is a comprehensive list of all untranslated strings. Please provide French translations for each:</p>
    
"""
    
    # Add each missing translation
    for i, (msgid, entry) in enumerate(missing, 1):
        location = ""
        if entry.comment:
            lines = entry.comment.strip().split('\n')
            if lines:
                # Extract file path from comment
                file_location = lines[0].replace('#:', '').strip()
                location = file_location
        
        html_content += f"""
    <div class="translation-item">
        <div style="display: flex; align-items: flex-start;">
            <span class="number">{i}</span>
            <div style="flex: 1;">
                <div class="msgid-label">English String:</div>
                <div class="msgid">{html.escape(msgid)}</div>
                
                <div class="msgstr-label">French Translation:</div>
                <textarea class="msgstr" placeholder="Enter French translation here..."></textarea>
                
"""
        if location:
            html_content += f'                <div class="context"><strong>Location:</strong> {html.escape(location)}</div>\n'
        
        html_content += """
            </div>
        </div>
    </div>
"""
    
    html_content += """
    <footer>
        <p>💡 Tip: Use browser's Find feature (Ctrl+F / Cmd+F) to search for specific strings</p>
        <p>Generated for translation completion | My Flying Box WordPress Plugin</p>
    </footer>
</body>
</html>
"""
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(html_content)
    
    print(f"✓ HTML template created: {output_file}")
    print(f"  Open this file in a browser to see a formatted list of missing translations.")


def create_csv_export(manager: TranslationManager, output_file: str = None) -> None:
    """Export missing translations as CSV"""
    if output_file is None:
        output_file = os.path.join(
            manager.lang_dir,
            f'missing-translations-{manager.locale}.csv'
        )
    
    parser = GettextParser(manager.po_file)
    parser.parse()
    
    missing = parser.get_missing_translations()
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write('ID,English,French,Location\n')
        for i, (msgid, entry) in enumerate(missing, 1):
            location = ""
            if entry.comment:
                lines = entry.comment.strip().split('\n')
                if lines:
                    location = lines[0].replace('#:', '').strip()
            
            # CSV escape
            msgid_escaped = f'"{msgid.replace(chr(34), chr(34)+chr(34))}"'
            location_escaped = f'"{location.replace(chr(34), chr(34)+chr(34))}"'
            
            f.write(f'{i},{msgid_escaped},"",{location_escaped}\n')
    
    print(f"✓ CSV export created: {output_file}")


def main():
    parser = argparse.ArgumentParser(
        description='Translation Filler Tool'
    )
    parser.add_argument(
        '--locale',
        default='fr_FR',
        help='Language locale (default: fr_FR)'
    )
    parser.add_argument(
        '--show-all',
        action='store_true',
        help='Show all missing translations with details'
    )
    parser.add_argument(
        '--create-template',
        action='store_true',
        help='Create HTML template for translators'
    )
    parser.add_argument(
        '--export-csv',
        action='store_true',
        help='Export missing translations as CSV'
    )
    
    args = parser.parse_args()
    
    script_dir = os.path.dirname(os.path.abspath(__file__))
    plugin_dir = os.path.dirname(script_dir)
    
    try:
        manager = TranslationManager(plugin_dir, args.locale)
        
        if not any([args.show_all, args.create_template, args.export_csv]):
            args.create_template = True
        
        if args.show_all:
            show_detailed_missing(manager)
        
        if args.create_template:
            create_translator_template(manager)
        
        if args.export_csv:
            create_csv_export(manager)
        
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
