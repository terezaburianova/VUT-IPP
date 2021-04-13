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


def err(msg, code):
    print(msg, file=sys.stderr)
    sys.exit(code)


def value_validity(attr_type, text):
    text_regex = {
        'var': r'^(GF|LF|TF)@[a-žA-Ž_\-$&%*!?][a-žA-Ž0-9_\-$&%*!?]*$',
        'label': r'^[a-žA-Ž_\-$&%*!?][a-žA-Ž0-9_\-$&%*!?]*$',
        'type': r'^(int|string|bool)$',
        'int': r'^[\-]?[0-9]+$',
        'bool': r'^(true|false)$',
        'string': r'^([^\s#\\\\]|\\[0-9]{3})*$',
        'nil': r'^nil$'
    }
    if attr_type == 'string' and text is None:
        text = ''
    text.replace("\\\\", "\\")
    try:
        if re.search(re.compile(text_regex[attr_type]), text) is None:
            return False
    except:
        err("Argument text is missing.", ERR_INVALID_STRUCT)
    return True


class Preparation:
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
        for key in ['CALL', 'LABEL', 'JUMP', 'JUMPIFEQS', 'JUMPIFNEQS']:
            self.instruction_dict[key] = ['label']
        for key in ['PUSHS', 'WRITE', 'EXIT', 'DPRINT']:
            self.instruction_dict[key] = ['symb']
        for key in ['MOVE', 'INT2CHAR', 'STRLEN', 'TYPE']:
            self.instruction_dict[key] = ['var', 'symb']
        for key in ['READ']:
            self.instruction_dict[key] = ['var', 'type']
        for key in ['ADD', 'SUB', 'MUL', 'IDIV', 'LT', 'GT', 'EQ', 'AND', 'OR', 'NOT', 'STRI2INT', 'CONCAT', 'GETCHAR',
                    'SETCHAR']:
            self.instruction_dict[key] = ['var', 'symb', 'symb']
        for key in ['JUMPIFEQ', 'JUMPIFNEQ']:
            self.instruction_dict[key] = ['label', 'symb', 'symb']
        for key in ['CLEARS', 'ADDS', 'SUBS', 'MULS', 'IDIVS', 'LTS', 'GTS', 'EQS', 'ANDS', 'ORS', 'NOTS', 'INT2CHARS', 'STRI2INTS']:
            self.instruction_dict[key] = []

    def argument_parse(self):
        parser = argparse.ArgumentParser(description='Add path to source or input. At least one has to be set.')
        parser.add_argument('--source')
        parser.add_argument('--input')
        args = parser.parse_args()
        if not (args.source or args.input):
            parser.error('Add -source or -input. See --help for more info.')
        if args.source:
            self.int_source = args.source
        if args.input:
            self.int_input = open(args.input, "r")

    def xml_parse(self):
        try:
            tree = ET.parse(self.int_source)
            self.root = tree.getroot()
        except:
            err("Invalid XML format.", ERR_INVALID_FORMAT)
        # * checks needed before sorting
        orders = []
        for child in list(self.root):
            if child.tag != 'instruction':
                err("Invalid XML structure: 'instruction' expected.", ERR_INVALID_STRUCT)
            try:
                if (int(child.get('order')) < 1) or (int(child.get('order')) in orders):
                    err("Invalid order.", ERR_INVALID_STRUCT)
                orders.append(int(child.get('order')))
            except:
                err("Invalid order.", ERR_INVALID_STRUCT)
        # * sort instructions based on "order" attribute

        for child in self.root.iter():
            try:
                self.root[:] = sorted(self.root.findall('instruction'), key=lambda child: int(child.get('order')))
            except:
                err("Invalid order.", ERR_INVALID_STRUCT)

        # TODO: razeni argumentu - nefunkcni!
        # for arg in self.root.iter():
        #     try:
        #         self.root[:] = sorted(self.root.findall("instruction/*"), key=lambda arg: arg.tag)
        #     except:
        #         err("wrong value", ERR_INVALID_STRUCT)

    def xml_validity(self):
        # * check program tag validity
        if self.root.tag != 'program':
            err("The 'program' tag is missing.", ERR_INVALID_STRUCT)
        if not all(item in ['language', 'name', 'description'] for item in self.root.attrib):
            err("Invalid attributes in 'program'.", ERR_INVALID_STRUCT)
        if ('language' not in self.root.attrib) or (self.root.attrib['language'] != 'IPPcode21'):
            err("Attribute 'language' in 'program missing or invalid.", ERR_INVALID_STRUCT)
        # * check instruction tag validity
        for child in self.root.findall('instruction'):
            if not all(item in ['opcode', 'order'] for item in child.attrib):
                err(f"Invalid attributes in 'instruction'.", ERR_INVALID_STRUCT)
            if ('opcode' not in child.attrib) or ('order' not in child.attrib):
                err("Missing 'instruction' attribute 'order' or 'opcode'.", ERR_INVALID_STRUCT)
            opcode = child.get("opcode").upper()
            if opcode not in self.instruction_dict:
                err("Invalid instruction opcode.", ERR_INVALID_STRUCT)
            args_current = []
            # * check instruction childern (args)
            argnum = 1
            for arg in list(child):
                if arg.tag != ('arg' + str(argnum)):  # * invalid tag name in args
                    err("Invalid tags.", ERR_INVALID_STRUCT)
                if (len(arg.attrib) != 1) or ('type' not in arg.attrib):
                    err("Invalid 'arg' attributes.", ERR_INVALID_STRUCT)
                type_attr = arg.get('type')
                valid_type_attr = ['int', 'bool', 'string', 'nil', 'label', 'type', 'var']
                if (type_attr is None) or (type_attr not in valid_type_attr):  # * invalid type attribute in arg tags
                    err("Invalid 'arg' attributes.", ERR_INVALID_STRUCT)
                # * check text validity
                text_valid = value_validity(type_attr, arg.text)
                if not text_valid:
                    err("Invalid text inside an argument.", ERR_INVALID_STRUCT)
                # * change type attribute in case of symb
                if type_attr in ['string', 'int', 'nil', 'bool']:
                    type_attr = 'symb'
                try:
                    if type_attr == 'var' and self.instruction_dict[opcode][argnum - 1] == 'symb':
                        type_attr = 'symb'
                except:
                    err("Invalid arguments.", ERR_INVALID_STRUCT)
                args_current.append(type_attr)
                argnum += 1
            if args_current != self.instruction_dict[opcode.upper()]:
                err("Invalid 'instruction' arguments.", ERR_INVALID_STRUCT)


class Frame:
    def __init__(self):
        self.variables = {}

    def define_variable(self, var_name):
        if var_name in self.variables:
            err(f"Variable '{var_name}' is already defined.", ERR_SEM)
        self.variables[var_name] = None

    def edit_variable(self, var_name, value, value_type):
        if value_type == 'int':
            try:
                value = int(value)
            except:
                err("Invalid int.", ERR_TYPES)

        if var_name not in self.variables:
            err(f"Variable '{var_name}' does not exist.", ERR_VAR)
        self.variables[var_name] = [value_type, value]

    def get_var_value(self, var_name, geterr=True):
        if var_name not in self.variables:
            err(f"Variable '{var_name}' does not exist.", ERR_VAR)
        if self.variables[var_name] is None:
            if geterr:
                err(f"The variable {var_name} has no value yet.", ERR_VALUE_MISSING)
            else:
                return '', ''
        return self.variables[var_name][1], self.variables[var_name][0]


class Labels:
    labels_storage = {}

    def __init__(self, xml_root):
        instr_list = xml_root.findall('instruction')
        for instruction in instr_list:
            if instruction.get('opcode') == 'LABEL':
                label_name = instruction[0].text
                if label_name in self.labels_storage:
                    err(f"Label {label_name} already exists.", ERR_SEM)
                self.labels_storage[label_name] = instr_list.index(instruction)


class Interpret:
    GF = None
    TF = None
    LF_stack = []
    LF = None
    call_stack = []
    data_stack = []
    current = 0
    label = None
    prep = None

    def __init__(self):
        self.prep = Preparation()
        self.GF = Frame()
        self.label = Labels(self.prep.root)
        instruction_list = self.prep.root.findall('instruction')
        self.current = 0
        while self.current < len(instruction_list):
            self.call_instruction(instruction_list[self.current].get('opcode').upper(), instruction_list[self.current])
            self.current += 1
        self.prep.int_input.close()

    def call_instruction(self, name: str, instr):
        if hasattr(self, name) and callable(instr_method := getattr(self, name)):
            instr_method(instr)

    def check_frame(self, frame_type):
        if frame_type == 'LF':
            if self.LF_stack:
                self.LF = self.LF_stack[-1]
            else:
                err("Frame does not exist.", ERR_FRAME)
        elif frame_type == 'TF':
            if self.TF is None:
                err("Frame does not exist.", ERR_FRAME)

    def return_frame(self, instr, var_index):
        var = instr[var_index].text.split('@', 1)
        self.check_frame(var[0])
        current_frame = getattr(self, var[0])
        return current_frame, var[1]

    def resolve_symb(self, instr, symb_index, geterr=True):
        symb_type = instr[symb_index].get('type')
        symb_val = None
        if symb_type == 'var':
            current_frame, var_name = self.return_frame(instr, symb_index)
            symb_val, symb_type = current_frame.get_var_value(var_name, geterr)
        elif symb_type == 'int':
            symb_val = int(instr[symb_index].text)
        elif symb_type == 'string':
            symb_val = self.replace_sequences(instr[symb_index].text)
        else:
            symb_val = instr[symb_index].text
        return symb_val, symb_type

    def replace_sequences(self, value):
        matches = re.findall(r'\\[0-9]{3}', value)
        for val in matches:
            try:
                char = chr(int(val[2:]))
            except:
                err("Error converting the escape sequence.", ERR_STRING)
            value = value.replace(val, char)
        return value

    def MOVE(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        symb_val, symb_type = self.resolve_symb(instr, 1)
        current_frame.edit_variable(var_name, symb_val, symb_type)

    def CREATEFRAME(self, _):
        self.TF = Frame()

    def PUSHFRAME(self, _):
        if self.TF is None:
            err("Temporary frame is not defined.", ERR_FRAME)
        self.LF_stack.append(self.TF)
        self.LF = self.LF_stack[-1]
        self.TF = None

    def POPFRAME(self, _):
        if not self.LF_stack:
            err("Local frame stack is empty.", ERR_FRAME)
        self.TF = self.LF_stack.pop()
        if self.LF_stack:
            self.LF = self.LF_stack[-1]
        else:
            self.LF = None

    def DEFVAR(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        current_frame.define_variable(var_name)

    def CALL(self, instr):
        self.call_stack.append(self.current)
        if instr[0].text not in self.label.labels_storage:
            err("Label does not exist.", ERR_SEM)
        self.current = self.label.labels_storage[instr[0].text]

    def RETURN(self, _):
        if not self.call_stack:
            err("Call-stack value missing.", ERR_VALUE_MISSING)
        self.current = self.call_stack.pop()
        # TODO: tvoreni a uklizeni ramcu

    def PUSHS(self, instr):
        v1, v1_t = self.resolve_symb(instr, 0)
        self.data_stack.append([v1, v1_t])

    def POPS(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        if not self.data_stack:
            err("Data stack is empty.", ERR_VALUE_MISSING)
        value = self.data_stack.pop()
        current_frame.edit_variable(var_name, value[0], value[1])

    def ADD(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v1, v1_t = self.resolve_symb(instr, 1)
        v2, v2_t = self.resolve_symb(instr, 2)
        if v1_t != 'int' or v2_t != 'int':
            err("Non-numeric value in arithmetic instruction.", ERR_TYPES)
        current_frame.edit_variable(var_name, v1 + v2, 'int')

    def SUB(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v1, v1_t = self.resolve_symb(instr, 1)
        v2, v2_t = self.resolve_symb(instr, 2)
        if v1_t != 'int' or v2_t != 'int':
            err("Non-numeric value in arithmetic instruction.", ERR_TYPES)
        current_frame.edit_variable(var_name, v1 - v2, 'int')

    def MUL(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v1, v1_t = self.resolve_symb(instr, 1)
        v2, v2_t = self.resolve_symb(instr, 2)
        if v1_t != 'int' or v2_t != 'int':
            err("Non-numeric value in arithmetic instruction.", ERR_TYPES)
        current_frame.edit_variable(var_name, v1 * v2, 'int')

    def IDIV(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v1, v1_t = self.resolve_symb(instr, 1)
        v2, v2_t = self.resolve_symb(instr, 2)
        if v1_t != 'int' or v2_t != 'int':
            err("Non-numeric value in arithmetic instruction.", ERR_TYPES)
        if v2 == 0:
            err("Division by zero.", ERR_VALUE_WRONG)
        current_frame.edit_variable(var_name, v1 // v2, 'int')

    def LT(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v1, v1_t = self.resolve_symb(instr, 1)
        v2, v2_t = self.resolve_symb(instr, 2)
        if v1_t == 'nil' or v2_t == 'nil':
            err("Comparison with nil.", ERR_TYPES)
        if v1_t != v2_t:
            err("Comparing values of two different types.", ERR_TYPES)
        if v1_t == 'bool':
            if v1 == 'true':
                v1 = True
            else:
                v1 = False
        if v2_t == 'bool':
            if v2 == 'true':
                v2 = True
            else:
                v2 = False
        current_frame.edit_variable(var_name, v1 < v2, 'bool')

    def GT(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v1, v1_t = self.resolve_symb(instr, 1)
        v2, v2_t = self.resolve_symb(instr, 2)
        if v1_t == 'nil' or v2_t == 'nil':
            err("Comparison with nil.", ERR_TYPES)
        if v1_t != v2_t:
            err("Comparing values of two different types.", ERR_TYPES)
        if v1_t == 'bool':
            if v1 == 'true':
                v1 = True
            else:
                v1 = False
        if v2_t == 'bool':
            if v2 == 'true':
                v2 = True
            else:
                v2 = False
        current_frame.edit_variable(var_name, v1 > v2, 'bool')

    def EQ(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v1, v1_t = self.resolve_symb(instr, 1)
        v2, v2_t = self.resolve_symb(instr, 2)
        if v1_t == 'nil' or v2_t == 'nil':
            current_frame.edit_variable(var_name, v1_t == v2_t, 'bool')
            return
        if v1_t != v2_t:
            err("Comparing values of two different types.", ERR_TYPES)
        if v1_t == 'bool':
            if v1 == 'true':
                v1 = True
            else:
                v1 = False
        if v2_t == 'bool':
            if v2 == 'true':
                v2 = True
            else:
                v2 = False
        current_frame.edit_variable(var_name, v1 == v2, 'bool')

    def AND(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v1, v1_t = self.resolve_symb(instr, 1)
        v2, v2_t = self.resolve_symb(instr, 2)
        if v1_t != 'bool' or v2_t != 'bool':
            err("Logical operators only accept bool values.", ERR_TYPES)
        if v1 == 'true':
            v1 = True
        else:
            v1 = False
        if v2 == 'true':
            v2 = True
        else:
            v2 = False
        current_frame.edit_variable(var_name, v1 and v2, 'bool')

    def OR(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v1, v1_t = self.resolve_symb(instr, 1)
        v2, v2_t = self.resolve_symb(instr, 2)
        if v1_t != 'bool' or v2_t != 'bool':
            err("Logical operators only accept bool values.", ERR_TYPES)
        if v1 == 'true':
            v1 = True
        else:
            v1 = False
        if v2 == 'true':
            v2 = True
        else:
            v2 = False
        current_frame.edit_variable(var_name, v1 or v2, 'bool')

    def NOT(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v, v_t = self.resolve_symb(instr, 1)
        if v_t != 'bool':
            err("Logical operators only accept bool values.", ERR_TYPES)
        if v == 'true':
            v = True
        else:
            v = False
        current_frame.edit_variable(var_name, not v, 'bool')

    def INT2CHAR(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v, v_t = self.resolve_symb(instr, 1)
        if v_t != 'int':
            err("INT2CHAR only accepts int value.", ERR_TYPES)
        try:
            value = chr(v)
        except:
            err("Unicode code is out of range.", ERR_STRING)
        current_frame.edit_variable(var_name, value, 'string')

    def STRI2INT(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        v, v_t = self.resolve_symb(instr, 1)
        i, i_t = self.resolve_symb(instr, 2)
        if v_t != 'string':
            err("STRI2INT only accepts string value.", ERR_TYPES)
        if i_t != 'int':
            err("STRI2INT: invalid index type.", ERR_TYPES)
        try:
            value = ord(v[i])
        except IndexError:
            err("STRI2INT: index out of range.", ERR_STRING)
        except:
            err("Unicode code is out of range.", ERR_STRING)
        current_frame.edit_variable(var_name, value, 'int')

    def READ(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        value = self.prep.int_input.readline()
        in_type = instr[1].text
        if in_type == 'int':
            if value_validity('int', value):
                value = int(value)
            else:
                in_type = 'nil'
                value = 'nil'
        elif in_type == 'bool':
            if value != 'true':
                value = 'false'
        elif in_type == 'string':
            value = value.rstrip()
        current_frame.edit_variable(var_name, value, in_type)

    def WRITE(self, instr):
        value, out_type = self.resolve_symb(instr, 0)
        if out_type == 'nil':
            value = ''
        print(value, end='')

    def CONCAT(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        s1, s1_t = self.resolve_symb(instr, 1)
        s2, s2_t = self.resolve_symb(instr, 2)
        if s1_t != 'string' or s2_t != 'string':
            err("Value is not of type string.", ERR_TYPES)
        current_frame.edit_variable(var_name, s1+s2, 'string')

    def STRLEN(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        s, s_t = self.resolve_symb(instr, 1)
        if s_t != 'string':
            err("Value is not of type string.", ERR_TYPES)
        current_frame.edit_variable(var_name, len(s), 'int')

    def GETCHAR(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        s, s_t = self.resolve_symb(instr, 1)
        i, i_t = self.resolve_symb(instr, 2)
        if s_t != 'string' or i_t != 'int':
            err("Invalid value types.", ERR_TYPES)
        i = int(i)
        try:
            char = s[i]
        except IndexError:
            err("GETCHAR: index out of range.", ERR_STRING)
        current_frame.edit_variable(var_name, char, 'string')

    def SETCHAR(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        s, s_t = current_frame.get_var_value(var_name)
        i, i_t = self.resolve_symb(instr, 1)
        ch, ch_t = self.resolve_symb(instr, 2)
        if ch_t != 'string' or i_t != 'int' or s_t != 'string':
            err("Invalid value types.", ERR_TYPES)
        i = int(i)
        try:
            s[i] = ch
        except IndexError:
            err("SETCHAR: index out of range.", ERR_STRING)
        current_frame.edit_variable(var_name, s, 'string')

    def TYPE(self, instr):
        current_frame, var_name = self.return_frame(instr, 0)
        t, t_t = self.resolve_symb(instr, 1, False)
        current_frame.edit_variable(var_name, t_t, 'string')

    def LABEL(self, _):
        pass

    def JUMP(self, instr):
        if instr[0].text not in self.label.labels_storage:
            err("Label does not exist.", ERR_SEM)
        self.current = self.label.labels_storage[instr[0].text]

    def JUMPIFEQ(self, instr):
        if instr[0].text not in self.label.labels_storage:
            err("Label does not exist.", ERR_SEM)
        v1, v1_t = self.resolve_symb(instr, 1)
        v2, v2_t = self.resolve_symb(instr, 2)
        if (v1_t == 'nil' or v2_t == 'nil') or (v1_t == v2_t):
            if v1_t == 'nil' and v2_t == 'nil':
                self.current = self.label.labels_storage[instr[0].text]
            elif v1_t == v2_t and v1 == v2:
                self.current = self.label.labels_storage[instr[0].text]
        else:
            err("Comparing values of two different types.", ERR_TYPES)

    def JUMPIFNEQ(self, instr):
        if instr[0].text not in self.label.labels_storage:
            err("Label does not exist.", ERR_SEM)
        v1, v1_t = self.resolve_symb(instr, 1)
        v2, v2_t = self.resolve_symb(instr, 2)
        if (v1_t == 'nil' or v2_t == 'nil') or (v1_t == v2_t):
            if v1_t == v2_t and v1 != v2:
                self.current = self.label.labels_storage[instr[0].text]
            if (v1_t == 'nil' and v2_t != 'nil') or (v1_t != 'nil' and v2_t == 'nil'):
                self.current = self.label.labels_storage[instr[0].text]
        else:
            err("Comparing values of two different types.", ERR_TYPES)

    def EXIT(self, instr):
        value, symb_type = self.resolve_symb(instr, 0)
        if symb_type != 'int':
            err("EXIT: Invalid value.", ERR_VALUE_WRONG)
        value = int(value)
        if value < 0 or value > 49:
            err("EXIT: Value out of range.", ERR_VALUE_WRONG)
        sys.exit(value)

    def DPRINT(self, instr):
        value, _ = self.resolve_symb(instr, 0)
        print(value, file=sys.stderr)

    def BREAK(self, instr):
        GF_val = self.GF.variables
        if self.TF:
            TF_val = self.TF.variables
        else:
            TF_val = 'None'
        if self.LF:
            LF_val = self.LF.variables
        else:
            LF_val = 'None'
        string = f"\nIndex in the instructions list: {self.current}\n" \
                 f"Instruction order: {instr.get('order')}\n" \
                 f"Global frame: \n{GF_val}\n" \
                 f"Temporary frame: \n{TF_val}\n" \
                 f"Local frame: \n{LF_val}\n" \
                 f"Local frames in stack: {len(self.LF_stack)}\n"
        print(string, file=sys.stderr)

    ########## STACK BONUS ##########
    def CLEARS(self, _):
        self.data_stack.clear()

    def ADDS(self, _):
        try:
            v2 = self.data_stack.pop()
            v1 = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v1[1] != 'int' or v2[1] != 'int':
            err("Non-numeric value in arithmetic instruction.", ERR_TYPES)
        self.data_stack.append([v1[0]+v2[0], 'int'])

    def SUBS(self, _):
        try:
            v2 = self.data_stack.pop()
            v1 = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v1[1] != 'int' or v2[1] != 'int':
            err("Non-numeric value in arithmetic instruction.", ERR_TYPES)
        self.data_stack.append([v1[0]-v2[0], 'int'])

    def MULS(self, _):
        try:
            v2 = self.data_stack.pop()
            v1 = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v1[1] != 'int' or v2[1] != 'int':
            err("Non-numeric value in arithmetic instruction.", ERR_TYPES)
        self.data_stack.append([v1[0]*v2[0], 'int'])

    def IDIVS(self, _):
        try:
            v2 = self.data_stack.pop()
            v1 = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v1[1] != 'int' or v2[1] != 'int':
            err("Non-numeric value in arithmetic instruction.", ERR_TYPES)
        if v2[0] == 0:
            err("Division by zero.", ERR_VALUE_WRONG)
        self.data_stack.append([v1[0] // v2[0], 'int'])

    def LTS(self, _):
        try:
            v2 = self.data_stack.pop()
            v1 = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v1[1] == 'nil' or v2[1] == 'nil':
            err("Comparison with nil.", ERR_TYPES)
        if v1[1] != v2[1]:
            err("Comparing values of two different types.", ERR_TYPES)
        if v1[1] == 'bool':
            if v1[0] == 'true':
                v1[0] = True
            else:
                v1[0] = False
        if v2[1] == 'bool':
            if v2[0] == 'true':
                v2[0] = True
            else:
                v2[0] = False
        value = v1[0] < v2[0]
        if value:
            value = 'true'
        else:
            value = 'false'
        self.data_stack.append([value, 'bool'])

    def GTS(self, _):
        try:
            v2 = self.data_stack.pop()
            v1 = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v1[1] == 'nil' or v2[1] == 'nil':
            err("Comparison with nil.", ERR_TYPES)
        if v1[1] != v2[1]:
            err("Comparing values of two different types.", ERR_TYPES)
        if v1[1] == 'bool':
            if v1[0] == 'true':
                v1[0] = True
            else:
                v1[0] = False
        if v2[1] == 'bool':
            if v2[0] == 'true':
                v2[0] = True
            else:
                v2[0] = False
        value = v1[0] > v2[0]
        if value:
            value = 'true'
        else:
            value = 'false'
        self.data_stack.append([value, 'bool'])

    def EQS(self, _):
        try:
            v2 = self.data_stack.pop()
            v1 = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v1[1] == 'nil' or v2[1] == 'nil':
            value = (v1[1] == v2[1])
            if value:
                value = 'true'
            else:
                value = 'false'
            self.data_stack.append([value, 'bool'])
            return
        if v1[1] != v2[1]:
            err("Comparing values of two different types.", ERR_TYPES)
        if v1[1] == 'bool':
            if v1[0] == 'true':
                v1[0] = True
            else:
                v1[0] = False
        if v2[1] == 'bool':
            if v2[0] == 'true':
                v2[0] = True
            else:
                v2[0] = False
        value = (v1[0] == v2[0])
        if value:
            value = 'true'
        else:
            value = 'false'
        self.data_stack.append([value, 'bool'])

    def ANDS(self, _):
        try:
            v2 = self.data_stack.pop()
            v1 = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v1[1] != 'bool' or v2[1] != 'bool':
            err("Logical operators only accept bool values.", ERR_TYPES)
        if v1[0] == 'true':
            v1[0] = True
        else:
            v1[0] = False
        if v2[0] == 'true':
            v2[0] = True
        else:
            v2[0] = False
        value = v1[0] and v2[0]
        if value:
            value = 'true'
        else:
            value = 'false'
        self.data_stack.append([value, 'bool'])

    def ORS(self, _):
        try:
            v2 = self.data_stack.pop()
            v1 = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v1[1] != 'bool' or v2[1] != 'bool':
            err("Logical operators only accept bool values.", ERR_TYPES)
        if v1[0] == 'true':
            v1[0] = True
        else:
            v1[0] = False
        if v2[0] == 'true':
            v2[0] = True
        else:
            v2[0] = False
        value = v1[0] or v2[0]
        if value:
            value = 'true'
        else:
            value = 'false'
        self.data_stack.append([value, 'bool'])

    def NOTS(self, _):
        try:
            v = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v[1] != 'bool':
            err("Logical operators only accept bool values.", ERR_TYPES)
        if v[0] == 'true':
            v[0] = True
        else:
            v[0] = False
        value = not v[0]
        if value:
            value = 'true'
        else:
            value = 'false'
        self.data_stack.append([value, 'bool'])

    def INT2CHARS(self, _):
        try:
            v = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v[1] != 'int':
            err("INT2CHAR only accepts int value.", ERR_TYPES)
        try:
            value = chr(v[0])
        except:
            err("Unicode code is out of range.", ERR_STRING)
        self.data_stack.append([value, 'string'])

    def STRI2INTS(self, _):
        try:
            i = self.data_stack.pop()
            v = self.data_stack.pop()
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if v[1] != 'string':
            err("STRI2INT only accepts string value.", ERR_TYPES)
        if i[1] != 'int':
            err("STRI2INT: invalid index type.", ERR_TYPES)
        try:
            value = ord(v[0][i[0]])
        except IndexError:
            err("STRI2INT: index out of range.", ERR_STRING)
        except:
            err("Unicode code is out of range.", ERR_STRING)
        self.data_stack.append([value, 'int'])

    def JUMPIFEQS(self, instr):
        try:
            v2 = self.data_stack.pop()
            v1 = self.data_stack.pop()
            lbl = instr[0].text
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if lbl not in self.label.labels_storage:
            err("Label does not exist.", ERR_SEM)
        if (v1[1] == 'nil' or v2[1] == 'nil') or (v1[1] == v2[1]):
            if v1[1] == 'nil' and v2[1] == 'nil':
                self.current = self.label.labels_storage[lbl]
            elif v1[1] == v2[1] and v1[0] == v2[0]:
                self.current = self.label.labels_storage[lbl]
        else:
            err("Comparing values of two different types.", ERR_TYPES)

    def JUMPIFNEQS(self, instr):
        try:
            v2 = self.data_stack.pop()
            v1 = self.data_stack.pop()
            lbl = instr[0].text
        except:
            err("The data stack is empty.", ERR_VALUE_MISSING)
        if lbl not in self.label.labels_storage:
            err("Label does not exist.", ERR_SEM)
        if (v1[1] == 'nil' or v2[1] == 'nil') or (v1[1] == v2[1]):
            if v1[1] == v2[1] and v1[0] != v2[0]:
                self.current = self.label.labels_storage[lbl]
            if (v1[1] == 'nil' and v2[1] != 'nil') or (v1[1] != 'nil' and v2[1] == 'nil'):
                self.current = self.label.labels_storage[lbl]
        else:
            err("Comparing values of two different types.", ERR_TYPES)


Interpret()
