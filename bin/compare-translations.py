#!/usr/bin/env python3
"""
POT/PO Comparison Tool
Compare POT and PO files to identify changes, removals, and new strings.

Usage:
    python3 bin/compare-translations.py                    # Compare POT and current PO
    python3 bin/compare-translations.py --show-removals    # Show deprecated strings
    python3 bin/compare-translations.py --show-new         # Show new strings only
"""

import os
import sys
import argparse
from pathlib import Path
from typing import Dict, Set, List, Tuple


class GettextComparator:
    """Compare POT and PO files"""
    
    def __init__(self, pot_file: str, po_file: str):
        self.pot_file = pot_file
        self.po_file = po_file
        self.pot_entries = {}
        self.po_entries = {}
    
    def parse_file(self, filepath: str) -> Dict:
        """Parse gettext file and return msgids"""
        entries = {}
        
        if not os.path.exists(filepath):
            return entries
        
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        
        import re
        # Find all msgid entries
        pattern = r'msgid\s+"([^"]*)"'
        for match in re.finditer(pattern, content):
            msgid = match.group(1)
            if msgid:  # Skip empty header
                entries[msgid] = True
        
        return entries
    
    def analyze(self) -> Dict:
        """Analyze differences between POT and PO"""
        self.pot_entries = self.parse_file(self.pot_file)
        self.po_entries = self.parse_file(self.po_file)
        
        pot_keys = set(self.pot_entries.keys())
        po_keys = set(self.po_entries.keys())
        
        new_strings = pot_keys - po_keys
        removed_strings = po_keys - pot_keys
        unchanged_strings = pot_keys & po_keys
        
        return {
            'total_pot': len(pot_keys),
            'total_po': len(po_keys),
            'new': sorted(list(new_strings)),
            'removed': sorted(list(removed_strings)),
            'unchanged': len(unchanged_strings),
        }
    
    def report(self, show_removals: bool = False, show_new: bool = False, show_unchanged: bool = False):
        """Print comparison report"""
        analysis = self.analyze()
        
        print("\n" + "=" * 80)
        print("POT/PO File Comparison")
        print("=" * 80)
        print(f"\nPOT File: {os.path.basename(self.pot_file)}")
        print(f"PO File:  {os.path.basename(self.po_file)}")
        
        print(f"\nStatistics:")
        print(f"  Total in POT:    {analysis['total_pot']} strings")
        print(f"  Total in PO:     {analysis['total_po']} strings")
        print(f"  Unchanged:       {analysis['unchanged']} strings")
        print(f"  New:             {len(analysis['new'])} strings")
        print(f"  Removed:         {len(analysis['removed'])} strings")
        
        # Show new strings
        if analysis['new']:
            print(f"\n🆕 NEW STRINGS ({len(analysis['new'])}):")
            print(str(chr(45)) * 80)
            for i, msgid in enumerate(analysis['new'][:15], 1):
                display = msgid[:75]
                if len(msgid) > 75:
                    display += "..."
                print(f"{i:2}. {display}")
            
            if len(analysis['new']) > 15:
                print(f"\n    ... and {len(analysis['new']) - 15} more")
        
        # Show removed strings
        if show_removals and analysis['removed']:
            print(f"\n❌ REMOVED STRINGS ({len(analysis['removed'])}):")
            print(str(chr(45)) * 80)
            for i, msgid in enumerate(analysis['removed'][:10], 1):
                display = msgid[:75]
                if len(msgid) > 75:
                    display += "..."
                print(f"{i:2}. {display}")
            
            if len(analysis['removed']) > 10:
                print(f"\n    ... and {len(analysis['removed']) - 10} more")
        
        print("\n" + "=" * 80 + "\n")
        
        return analysis


class TranslationManager:
    """Placeholder for TranslationManager"""
    def __init__(self, plugin_dir: str, locale: str = 'fr_FR'):
        self.plugin_dir = plugin_dir
        self.lang_dir = os.path.join(plugin_dir, 'lang')
        self.locale = locale
        self.domain = 'my-flying-box'
        self.pot_file = os.path.join(self.lang_dir, f'{self.domain}.pot')
        self.po_file = os.path.join(self.lang_dir, f'{self.domain}-{locale}.po')


def main():
    parser = argparse.ArgumentParser(
        description='Compare POT and PO files'
    )
    parser.add_argument(
        '--locale',
        default='fr_FR',
        help='Language locale (default: fr_FR)'
    )
    parser.add_argument(
        '--show-removals',
        action='store_true',
        help='Show removed strings'
    )
    parser.add_argument(
        '--show-new',
        action='store_true',
        help='Show only new strings (one per line for scripting)'
    )
    
    args = parser.parse_args()
    
    script_dir = os.path.dirname(os.path.abspath(__file__))
    plugin_dir = os.path.dirname(script_dir)
    
    try:
        manager = TranslationManager(plugin_dir, args.locale)
        
        comparator = GettextComparator(manager.pot_file, manager.po_file)
        analysis = comparator.analyze()
        
        if args.show_new:
            # Output new strings one per line for scripting
            for msgid in analysis['new']:
                print(msgid)
        else:
            # Show full report
            comparator.report(show_removals=args.show_removals)
            
            # Exit with status code if there are unreported issues
            if analysis['new']:
                sys.exit(1)
    
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
