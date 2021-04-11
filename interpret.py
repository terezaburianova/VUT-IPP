#!/usr/bin/env python3.8
"""VUT FIT - IPP, Project 2, IPPcode21 interpret. 
Author: Tereza Burianova (xburia28@vutbr.cz)
"""

import argparse
import xml.etree.ElementTree as ET
import sys
import re
import string

ERR_INVALID_FORMAT = 31
ERR_INVALID_STRUCT = 32
ERR_SEM = 52
ERR_TYPES = 53
ERR_VAR = 54
ERR_FRAME = 55
ERR_VALUE_MISSING = 56
ERR_VALUE_WRONG = 57
ERR_STRING = 58

class preparation:
    int_source = sys.stdin
    int_input = sys.stdin
    instruction_dict = {}
    root = None

    def __init__(self):
        self.argument_parse()
        self.fill_dictionary()
        self.xml_parse()
        self.xml_validity()
        
    def fill_dictionary(self):
        for key in ['CREATEFRAME', 'PUSHFRAME', 'POPFRAME', 'RETURN', 'BREAK']:
            self.instruction_dict[key] = []
        for key in ['DEFVAR', 'POPS']:
            self.instruction_dict[key] = ['var']
        for key in ['CALL', 'LABEL', 'JUMP']:
            self.instruction_dict[key] = ['label']
        for key in ['PUSHS', 'WRITE', 'EXIT', 'DPRINT']:
            self.instruction_dict[key] = ['symb']
        for key in ['MOVE', 'INT2CHAR', 'STRLEN', 'TYPE']:
            self.instruction_dict[key] = ['var', 'symb']
        for key in ['READ']:
            self.instruction_dict[key] = ['var', 'type']
        for key in ['ADD', 'SUB', 'MUL', 'IDIV', 'LT', 'GT', 'EQ', 'AND', 'OR', 'NOT', 'STRI2INT', 'CONCAT', 'GETCHAR', 'SETCHAR']:
            self.instruction_dict[key] = ['var', 'symb', 'symb']
        for key in ['JUMPIFEQ', 'JUMPIFNEQ']:
            self.instruction_dict[key] = ['label', 'symb', 'symb']

    def argument_parse(self):
        parser = argparse.ArgumentParser(description='Parse the arguments.')
        parser.add_argument('--source')
        parser.add_argument('--input')
        args = parser.parse_args()
        if not (args.source or args.input):
            parser.error('Add -source or -input. See --help for more info.')
        elif (args.source):
            self.int_source = args.source
        else:
            self.int_input = args.int_input

    def xml_parse(self):
        tree = ET.parse(self.int_source)
        self.root = tree.getroot()
        #* check needed before sorting
        for child in list(self.root):
            if (child.tag != 'instruction'):
                err("Invalid XML structure: 'instruction' expected.", ERR_INVALID_STRUCT)
        #* sort instructions based on "order" attribute
        for child in self.root.iter():
                self.root[:] = sorted(self.root.findall('instruction'), key=lambda child: int(child.get('order')))

    def xml_validity(self):
        #* check program tag validity
        if (self.root.tag != 'program'):
            err("The 'program' tag is missing.", ERR_INVALID_STRUCT)
        if (not all(item in ['language', 'name', 'description'] for item in self.root.attrib)):
            err("Invalid attributes in 'program'.", ERR_INVALID_STRUCT)
        if (('language' not in self.root.attrib) or (self.root.attrib['language'] != 'IPPcode21')):
            err("Attribute 'language' in 'program missing or invalid.", ERR_INVALID_STRUCT)
        #* check instruction tag validity
        for child in self.root.findall('instruction'):
            if (not all(item in ['opcode', 'order'] for item in child.attrib)):
                err(f"Invalid attributes in 'instruction'.", ERR_INVALID_STRUCT)
            if ('opcode' not in child.attrib or 'order' not in child.attrib):
                err("Missing 'instruction' attribute 'order' or 'opcode'.", ERR_INVALID_STRUCT)
            opcode = child.get("opcode").upper()
            if opcode not in self.instruction_dict:
                err("Invalid instruction opcode.", ERR_INVALID_STRUCT)
            args_current = []
            #* check instruction childern (args)
            argnum = 1
            for arg in list(child):
                if (arg.tag != ('arg' + str(argnum))): #* invalid tag name in args
                    err("Invalid tags.", ERR_INVALID_STRUCT)
                if (len(arg.attrib) != 1 or ('type' not in arg.attrib)):
                    err("Invalid 'arg' attributes.", ERR_INVALID_STRUCT)
                type_attr = arg.get('type')
                valid_type_attr = ['int', 'bool', 'string', 'nil', 'label', 'type', 'var']
                if (type_attr == None or type_attr not in valid_type_attr): #* invalid type attribute in arg tags
                    err("Invalid 'arg' attributes.", ERR_INVALID_STRUCT)
                #* check text validity
                self.text_validity(type_attr, arg.text)
                #* change type attribute in case of symb
                if (type_attr in ['string', 'int', 'nil', 'bool']):
                    type_attr = 'symb'
                if (type_attr == 'var' and self.instruction_dict[opcode][argnum-1] == 'symb'):
                    type_attr = 'symb'
                args_current.append(type_attr)
                argnum += 1
            if (args_current != self.instruction_dict[opcode.upper()]):
                err("Invalid 'instruction' arguments.", ERR_INVALID_STRUCT)

    def text_validity(self, attr_type, text):
        text_regex = {
            'var' : r'^(GF|LF|TF)@[a-žA-Ž_\-$&%*!?][a-žA-Ž0-9_\-$&%*!?]*$',
            'label' : r'^[a-žA-Ž_\-$&%*!?][a-žA-Ž0-9_\-$&%*!?]*$',
            'type' : r'^(int|string|bool)$',
            'int' : r'^[\-]?[0-9]+$',
            'bool' : r'^(true|false)$',
            'string' : r'^([^\s#\\\\]|\\\\[0-9]{3})*$',
            'nil' : r'^nil$'
        }
        if (re.search(re.compile(text_regex[attr_type]), text) == None):
            err("Invalid argument text.", ERR_INVALID_STRUCT)


def err(msg, code):
    print(msg, file=sys.stderr)
    sys.exit(code)

preparation()