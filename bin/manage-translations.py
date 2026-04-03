#!/usr/bin/env python3
"""
Translation Management Tool for My Flying Box WordPress Plugin

Extracts translatable strings from PHP source, regenerates POT,
merges into PO, and compiles MO — using GNU gettext tools.

Usage:
    python3 bin/manage-translations.py --extract              # Regenerate POT + merge PO + compile MO
    python3 bin/manage-translations.py --locale fr_FR --report
    python3 bin/manage-translations.py --locale fr_FR --generate-mo
"""

import os
import sys
import json
import argparse
import subprocess
import shutil
import tempfile
from pathlib import Path
from dataclasses import dataclass, field
from typing import Dict, List, Tuple, Optional
import re
import struct
import io


@dataclass
class TranslationEntry:
    """Represents a single translation entry"""
    msgid: str
    msgstr: str
    msgid_plural: Optional[str] = None
    msgstr_plural: List[str] = field(default_factory=list)
    comment: str = ""
    locations: List[str] = field(default_factory=list)
    is_fuzzy: bool = False
    is_plural: bool = False


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
    
    def _parse_entry(self, text: str) -> Optional[TranslationEntry]:
        """Parse a single entry block"""
        lines = text.split('\n')
        entry = TranslationEntry(msgid="", msgstr="")
        
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
    
    def get_missing_translations(self) -> List[Tuple[str, TranslationEntry]]:
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


class MOGenerator:
    """Generate MO files from PO data"""
    
    MAGIC = 0x950412DE
    VERSION = 0
    
    def __init__(self, parser: GettextParser):
        self.parser = parser
    
    def generate(self, output_path: str) -> None:
        """Generate MO file"""
        entries = [(e.msgid, e.msgstr) for e in self.parser.entries.values() 
                   if e.msgid]
        
        if not entries:
            raise ValueError("No entries to write")
        
        # Sort by msgid
        entries.sort(key=lambda x: x[0])
        
        # Build strings
        keys_offsets = []
        values_offsets = []
        strings = io.BytesIO()
        
        for msgid, msgstr in entries:
            msgid_bytes = msgid.encode('utf-8')
            msgstr_bytes = msgstr.encode('utf-8')
            
            keys_offsets.append((len(msgid_bytes), strings.tell()))
            strings.write(msgid_bytes)
            
            values_offsets.append((len(msgstr_bytes), strings.tell()))
            strings.write(msgstr_bytes)
        
        strings_data = strings.getvalue()
        
        # Calculate offsets
        keyoffset = 7 * 4 + 16 * len(entries)
        valueoffset = keyoffset + 8 * len(entries)
        
        # Build header
        header = struct.pack(
            'Iiiiiii',
            self.MAGIC,
            self.VERSION,
            len(entries),
            7 * 4,
            valueoffset,
            0,
            0
        )
        
        # Build key and value tables
        koffsets = io.BytesIO()
        voffsets = io.BytesIO()
        
        for length, offset in keys_offsets:
            koffsets.write(struct.pack('ii', length, keyoffset + offset))
        
        for length, offset in values_offsets:
            voffsets.write(struct.pack('ii', length, valueoffset + offset))
        
        # Write MO file
        with open(output_path, 'wb') as f:
            f.write(header)
            f.write(koffsets.getvalue())
            f.write(voffsets.getvalue())
            f.write(strings_data)
        
        print(f"✓ MO file generated: {output_path}")


class TranslationManager:
    """Main translation management class"""
    
    # WordPress gettext keywords for xgettext
    WP_KEYWORDS = [
        '__', '_e', '_n:1,2', '_x:1,2c', '_ex:1,2c',
        '_nx:4c,1,2', '_nx_noop:3c,1,2', '_n_noop:1,2',
        '__ngettext:1,2', '__ngettext_noop:1,2',
        'esc_attr__', 'esc_html__', 'esc_attr_e', 'esc_html_e',
        'esc_attr_x:1,2c', 'esc_html_x:1,2c', 'esc_js',
    ]

    # Directories to exclude from string extraction
    EXCLUDE_DIRS = ['vendor', 'node_modules', 'includes/lib/php-lce', 'bin']

    def __init__(self, plugin_dir: str, locale: str = 'fr_FR'):
        self.plugin_dir = plugin_dir
        self.lang_dir = os.path.join(plugin_dir, 'lang')
        self.locale = locale
        self.domain = 'my-flying-box'
        
        self.pot_file = os.path.join(self.lang_dir, f'{self.domain}.pot')
        self.po_file = os.path.join(self.lang_dir, f'{self.domain}-{locale}.po')
        self.mo_file = os.path.join(self.lang_dir, f'{self.domain}-{locale}.mo')

    def _check_tool(self, name: str) -> bool:
        return shutil.which(name) is not None

    def _find_php_files(self) -> List[str]:
        """Find all PHP files to scan, excluding vendor/lib directories."""
        result = []
        for root, dirs, files in os.walk(self.plugin_dir):
            rel = os.path.relpath(root, self.plugin_dir)
            # Skip excluded directories
            skip = False
            for excl in self.EXCLUDE_DIRS:
                if rel == excl or rel.startswith(excl + os.sep):
                    skip = True
                    break
            if skip:
                continue
            for f in files:
                if f.endswith('.php'):
                    result.append(os.path.relpath(os.path.join(root, f), self.plugin_dir))
        result.sort()
        return result

    def extract_pot(self) -> None:
        """Extract translatable strings from PHP source into a fresh POT."""
        if not self._check_tool('xgettext'):
            raise RuntimeError("xgettext not found. Install gettext: sudo apt-get install gettext")

        php_files = self._find_php_files()
        if not php_files:
            raise RuntimeError("No PHP files found to extract strings from")

        # Write file list to a temporary file
        with tempfile.NamedTemporaryFile(mode='w', suffix='.txt', delete=False) as tmp:
            tmp.write('\n'.join(php_files))
            filelist_path = tmp.name

        try:
            cmd = [
                'xgettext',
                '--files-from=' + filelist_path,
                '--language=PHP',
                '--from-code=UTF-8',
                '--add-comments=translators',
                '--package-name=My Flying Box',
                '--package-version=1.0',
                '--msgid-bugs-address=http://wordpress.org/tag/WordPress-Plugin-Template',
                '--output=' + self.pot_file,
            ]
            for kw in self.WP_KEYWORDS:
                cmd.append(f'--keyword={kw}')

            result = subprocess.run(cmd, cwd=self.plugin_dir, capture_output=True, text=True)
            if result.returncode != 0:
                raise RuntimeError(f"xgettext failed:\n{result.stderr}")

            count = 0
            with open(self.pot_file, 'r', encoding='utf-8') as f:
                for line in f:
                    if line.startswith('msgid ') and line.strip() != 'msgid ""':
                        count += 1
            print(f"✓ POT generated: {self.pot_file} ({count} strings)")
        finally:
            os.unlink(filelist_path)

    def merge_po(self) -> None:
        """Merge new POT strings into existing PO file using msgmerge."""
        if not self._check_tool('msgmerge'):
            raise RuntimeError("msgmerge not found. Install gettext: sudo apt-get install gettext")
        if not os.path.exists(self.po_file):
            raise FileNotFoundError(f"PO file not found: {self.po_file}")
        if not os.path.exists(self.pot_file):
            raise FileNotFoundError(f"POT file not found: {self.pot_file}")

        result = subprocess.run(
            ['msgmerge', '--update', '--no-fuzzy-matching', '--backup=simple', self.po_file, self.pot_file],
            capture_output=True, text=True
        )
        if result.returncode != 0:
            raise RuntimeError(f"msgmerge failed:\n{result.stderr}")
        print(f"✓ PO merged: {self.po_file}")

    def compile_mo(self) -> None:
        """Compile PO to MO using msgfmt."""
        if not self._check_tool('msgfmt'):
            raise RuntimeError("msgfmt not found. Install gettext: sudo apt-get install gettext")
        if not os.path.exists(self.po_file):
            raise FileNotFoundError(f"PO file not found: {self.po_file}")

        result = subprocess.run(
            ['msgfmt', '-o', self.mo_file, self.po_file],
            capture_output=True, text=True
        )
        if result.returncode != 0:
            raise RuntimeError(f"msgfmt failed:\n{result.stderr}")
        size = os.path.getsize(self.mo_file)
        print(f"✓ MO compiled: {self.mo_file} ({size} bytes)")

    def extract(self) -> None:
        """Full pipeline: extract POT → merge PO → compile MO."""
        self.extract_pot()
        if os.path.exists(self.po_file):
            self.merge_po()
            self.compile_mo()
        else:
            print(f"  (no PO file at {self.po_file} — skipping merge/compile)")
    
    def report(self) -> None:
        """Print translation report"""
        parser = GettextParser(self.po_file)
        parser.parse()
        
        stats = parser.get_statistics()
        missing = parser.get_missing_translations()
        
        print("\n" + "=" * 70)
        print(f"Translation Report: {self.locale}")
        print("=" * 70)
        print(f"Total strings:     {stats['total']}")
        print(f"Translated:        {stats['translated']} ({stats['percentage']}%)")
        print(f"Untranslated:      {stats['untranslated']}")
        print("=" * 70)
        
        if missing:
            print(f"\n🔍 MISSING TRANSLATIONS ({len(missing)}):\n")
            for msgid, entry in missing[:20]:  # Show first 20
                display = msgid[:60]
                if len(msgid) > 60:
                    display += "..."
                print(f"  [ ] {display}")
            
            if len(missing) > 20:
                print(f"\n  ... and {len(missing) - 20} more untranslated strings")
        else:
            print("\n✓ All strings are translated!")
        
        print("\n" + "=" * 70 + "\n")
    
    def generate_mo(self) -> None:
        """Generate MO file from PO (uses msgfmt if available, fallback to Python)"""
        if self._check_tool('msgfmt'):
            self.compile_mo()
        else:
            parser = GettextParser(self.po_file)
            parser.parse()
            generator = MOGenerator(parser)
            generator.generate(self.mo_file)
            if os.path.exists(self.mo_file):
                size = os.path.getsize(self.mo_file)
                print(f"✓ MO file size: {size} bytes")
    
    def export_missing_json(self, output_file: str = None) -> None:
        """Export missing translations as JSON"""
        if output_file is None:
            output_file = os.path.join(self.lang_dir, 'missing-translations.json')
        
        parser = GettextParser(self.po_file)
        parser.parse()
        
        missing = parser.get_missing_translations()
        
        data = {
            'locale': self.locale,
            'timestamp': __import__('datetime').datetime.now().isoformat(),
            'missing_count': len(missing),
            'missing_strings': [msgid for msgid, _ in missing]
        }
        
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
        
        print(f"✓ Exported missing translations to {output_file}")


def main():
    parser = argparse.ArgumentParser(
        description='Translation Management Tool for My Flying Box'
    )
    parser.add_argument(
        '--locale',
        default='fr_FR',
        help='Language locale (default: fr_FR)'
    )
    parser.add_argument(
        '--extract',
        action='store_true',
        help='Extract strings from PHP source → regenerate POT → merge PO → compile MO'
    )
    parser.add_argument(
        '--report',
        action='store_true',
        help='Show translation statistics and missing strings'
    )
    parser.add_argument(
        '--generate-mo',
        action='store_true',
        help='Generate MO file from PO file'
    )
    parser.add_argument(
        '--export-missing-json',
        action='store_true',
        help='Export missing translations as JSON'
    )
    
    args = parser.parse_args()
    
    # Get plugin directory
    script_dir = os.path.dirname(os.path.abspath(__file__))
    plugin_dir = os.path.dirname(script_dir)
    
    try:
        manager = TranslationManager(plugin_dir, args.locale)
        
        # If no action specified, show report
        if not any([args.extract, args.report, args.generate_mo, args.export_missing_json]):
            args.report = True
        
        if args.extract:
            manager.extract()
        
        if args.report:
            manager.report()
        
        if args.generate_mo:
            manager.generate_mo()
        
        if args.export_missing_json:
            manager.export_missing_json()
        
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
