<?php

#JSN:xbieli05

#Author: Marek Bielik
#E-mail: xbieli05@stud.fit.vutbr.cz
#Date:   February 2015
#Info:   Script for converting JSON to XML


#############################################################################################
# SCRIPT INIT
#############################################################################################

#ERROR codes
define('NO_ERR', 0);
define('ARG_ERR', 1);
define('READ_ERR', 2);
define('WRITE_ERR', 3);
define('FORMAT_ERR', 4);
define('ARG_E_NAME_ERR', 50);
define('E_NAME_ERR', 51);

#xml header
define("XML_HEADER", "<?xml version=\"1.0\" encoding=\"UTF-8\"?>");

#xml valid characters for names
define('NameStartChar', '_:A-Z_a-z\\xC0-\\xD6\\xD8-\\xF6\\xF8-\\x{2FF}\\x{370}-\\x{37D}\\x{37F}-\\x{1FFF}\\x{200C}-\\x{200D}\\x{2070}-\\x{218F}\\x{2C00}-\\x{2FEF}\\x{3001}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFFD}\\x{10000}-\\x{EFFFF}');
define("NameChar", NameStartChar . '-.\\-0-9\\xB7\\x{0300}-\\x{036F}\\x{203F}-\\x{2040}');

#argument patterns
$longoptions = array("help",
                     "input:",
                     "output:",
                     "array-name:",
                     "item-name:",
                     "array-size",
                     "index-items",
                     "start:");

$shortoptions = "h:".
                "n".
                "r:".
                "s".
                "i".
                "l".
                "c".
                "a".
                "t";

#############################################################################################
# SCRIPT FUNCTIONS
#############################################################################################

#prints help message
function print_help () {
  #terminal width:|                                                                               |
           printf("Usage: php jsn.php [Options]\n".
                  "Script converts JSON file format to XML\n".
                  "Options:\n".
                  "--help                       shows this message\n".
                  "--input=filename             specifies input file in JSON format\n".
                  "--output=filename            specifies converted output file in XML format\n".
                  "-h=subst                     substitutes prohibited characters for 'subst'\n".
                  "-n                           an XML header won't be included into output file\n".
                  "-r=root-elem                 specifies name of the root element in XML\n".
                  "--array-name=array-element   a name of array-wrapping elements\n".
                  "--item-name=item-element     a name of item-wrapping elements in arrays\n".
                  "-s                           transforms string values to elements\n".
                  "-i                           transforms integer values to elements\n".
                  "-l                           transforms literals (true, false, null)\n".
                  "                             to elements\n".
                  "-c                           transforms XML problematic characters\n".
                  "-a, --array-size             adds a 'size' array attribute\n".
                  "-t, --index-items            adds an 'index' item attribute \n".
                  "--start=n                    initializes the 'index' attribute\n");
}

#function for replacing certain characters in given string
#these characters are problematic in XML format
function replace_invalid ($string) {
  
  global $options;

  if ( ! isset($options['c']) )
    return $string;
  $string = mb_ereg_replace("&", "&amp;", $string);
  $string = mb_ereg_replace(">", "&gt;", $string);
  $string = mb_ereg_replace("<", "&lt;", $string);
  $string = mb_ereg_replace("'", "&apos;", $string);
  $string = mb_ereg_replace("\"", "&quot;", $string);

  return $string;
}

#function prints given string to output (stdout or file)
#auxiliary function used just in order to simplify the code
function auxwrite ($string) {
  
  global $options;
  
  fwrite($options['of_handler'], $string);
}

#function for validation XML names
#$name is string type
function valid_xml($name) {
  try {
    new DOMElement($name);
    return true;
  }
  catch (DOMException $exception) {
    return false;
  }
}

#function replaces invalid XML characters in given string
function replace_inv($name) {

  global $options;

  $name = preg_replace('/^([Xx][Mm][Ll]|[^' .NameStartChar. '])/ux', $options['h'], $name);
  $name = preg_replace('/[^' . NameChar . ']/ux', $options['h'], $name);
  if (!valid_xml($name))
    exit (E_NAME_ERR);
  return $name;
}

#function for processing arrays from the given JSON file
#argument $item represents one array in JSON format
#This function uses a write() function for writing the
# elements and sets various arguments depending on the 
#element being written. The first argument is always 
# a 'tag' which determines the right operation.
#List of used tags:
#arr_s -- array starting tag           
#arr_e -- array ending tag
#item_s -- item in array starting tag (other array or objedt)
#item_e -- item in array ending tag
#item_int_string  -- tag for an integer or string in array
#item_bool_null -- tag for boolean or null in array
#elem_s -- starting tag of element in object
#elem_e -- ending tag of element in object
#obj_int_str -- tag for integer or string in object
#obj_bool_null -- tag for boolean or null in object
function print_array($item) {

  global $options;
  $index = $options['start'];
  
  write('arr_s', count($item));

  #run through the whole array
  foreach ($item as $name => $value) {
    
    switch (gettype($value)) {
      
      case "array":
        write('item_s', $index);
        print_array($value);
        write('item_e');
      break;
     
      case "integer":
      case "double":
        $value = floatval($value);
        $value = intval($value);
      case "string":
        write('item_int_string', $value, $index);
      break;

      case "boolean":
      case "NULL":
        write('item_bool_null', $value, $index);
      break;
      
      case "object":
        write('item_s', $index);
        print_object($value);
        write('item_e');
      break;

    }
    $index = $index + 1;
  }

  write('arr_e');
} 

#function for processing objects from the given JSON file
#this function is similar to print_array(), see for further info
function print_object($item) {

  global $options;
  
  foreach ($item as $name => $value) {
   
    $name = replace_inv($name);

    switch (gettype($value)) {
      
      case "array":
        write('elem_s', $name);
        print_array($value);
        write('elem_e', $name);
      break;

      case "object":
        write('elem_s', $name);
        print_object($value);
        write('elem_e', $name);
      break;

      case "integer":
      case "string":
        write('obj_int_str', $name, $value);
      break;

      case "boolean":
      case "NULL":
        write('obj_bool_null', $name, $value);
      break;
    }
  }
}

#function for writing and processing xml elements
#this function uses a variable-lenght argument list depending on the type
#of an actual operation being processed
#See print_array() function for further information and 
#parameters specification
function write ($item) {
  
  global $options;

  #get arguments
  $args = func_get_args();

  #decide which operation should be done
  switch ($args[0]) {
    
    case 'item_s':
      if ($options['index'] == true)
        auxwrite("<".$options['item-name']." index=\"".$args[1]."\">\n");
      else
        auxwrite("<".$options['item-name'].">\n");
    break;

    case 'item_e':
      auxwrite("</".$options['item-name'].">\n");
    break;

    case 'arr_s':
      if ($options['size'] == true)
        auxwrite("<".$options['array-name']." size=\"".$args[1]."\">\n");
      else
        auxwrite("<".$options['array-name'].">\n");
    break;
    
    case 'arr_e':
      auxwrite("</".$options['array-name'].">\n");
    break;

    case 'item_int_string':
      $args[1] =  replace_invalid($args[1]);
      auxwrite("<".$options['item-name']);
     
      if ($options['index'] == true)
        auxwrite(" index=\"".$args[2]."\"");

      if ( ! isset($options['i']) && gettype($args[1]) == "integer" ||
           ! isset($options['s']) && gettype($args[1]) == "string")
        auxwrite(" value=\"".$args[1]."\" />\n");
      else
        auxwrite(">".$args[1]."</".$options['item-name'].">\n");
    break;

    case 'item_bool_null':
      if (gettype($args[1]) == "boolean" && $args[1] == true)
        $args[1] = 'true';
      elseif (gettype($args[1]) == "boolean" && $args[1] == false)
        $args[1] = 'false';
      else
        $args[1] = 'null';

      auxwrite("<".$options['item-name']);
      
      if ($options['index'] == true)
        auxwrite(" index=\"".$args[2]."\"");
      if ( ! isset($options['l']) )
        auxwrite(" value=\"".$args[1]."\"/>\n");
      else
        auxwrite("><".$args[1]."/></".$options['item-name'].">\n");
    break;

    case 'elem_s':
      auxwrite("<".$args[1].">\n");
    break;

    case 'elem_e':
      auxwrite("</".$args[1].">\n");
    break;

    case 'obj_int_str':
      $args[2] = replace_invalid($args[2]);
      auxwrite("<".$args[1]);

      if ( ! isset($options['i']) && gettype($args[2]) == "integer" ||
           ! isset($options['s']) && gettype($args[2]) == "string")
        auxwrite(" value=\"".$args[2]."\" />\n");
      else
        auxwrite(">".$args[2]."</".$args[1].">\n");

    break;

    case 'obj_bool_null':
      if (gettype($args[2]) == "boolean" && $args[2] == true)
        $args[2] = 'true';
      elseif (gettype($args[2]) == "boolean" && $args[2] == false)
        $args[2] = 'false';
      else
        $args[2] = 'null';

      auxwrite("<".$args[1]);
      if ( ! isset($options['l']) )
        auxwrite(" value=\"".$args[2]."\"/>\n");
      else
        auxwrite("><".$args[2]."/></".$args[1].">\n");   
    break;
  }
}

#############################################################################################
# SCRIPT START
#############################################################################################

#debug mode
#$debug = 'ON';
$debug = 'OFF';

# ARGUMENTS PROCESSING

#save options passed to the script
$options = getopt($shortoptions, $longoptions);

#get number of options
$arg_num = $argc -1;

#check options (duplicity and validity)
if ($arg_num != count($options, COUNT_RECURSIVE))
  exit(ARG_ERR);

#in case of --help 
if ( isset($options['help']) ) {
  if ($arg_num == 1) {
    print_help();
    exit(NO_ERR);
  }
  else 
    exit(ARG_ERR);
}

#read input file or read stdin
if ( isset($options['input']) ) {
  if ( false === $ifile = file_get_contents($options['input']) )
    exit(READ_ERR);
  }
else
  $ifile = file_get_contents('php://stdin'); 
    if ($debug == 'ON') {echo "INPUT:\n"; print_r($ifile); echo "\n\n";}

#open output file or open stdout
if ( isset($options['output']) ) {
  if ( false === $options['of_handler'] = fopen($options['output'], 'w') )
    exit(WRITE_ERR);
  }
else
  $options['of_handler'] = fopen('php://stdout', 'w');

#-h(substitution) option processing
if ( ! isset($options['h']) )
  $options['h'] = "-";

#root-element option processing
if ( isset($options['r']) ) {
  if (! valid_xml($options['r']))
    exit(ARG_E_NAME_ERR);
}

#array-name option processing
if ( isset($options['array-name']) ) {
  if (! valid_xml($options['array-name']))
    exit(ARG_E_NAME_ERR);
}
else
  $options['array-name'] = "array";

#item-name option processing
if ( isset($options['item-name']) ) {
  if (! valid_xml($options['item-name']))
    exit(ARG_E_NAME_ERR);
}
else
  $options['item-name'] = "item";

#array-size option processing
if ( (isset($options['a']) && ! isset($options['array-size'])) ||
     (! isset($options['a']) && isset($options['array-size'])) )
  $options['size'] = true;
elseif ( ! isset($options['a']) && ! isset($options['array-size']) )
  $options['size'] = false;
else
  exit(ARG_ERR);

#item-index option processing
if ( (isset($options['t']) && ! isset($options['index-items'])) ||
     (! isset($options['t']) && isset($options['index-items'])) )
  $options['index'] = true;
elseif ( ! isset($options['t']) && ! isset($options['index-items']) )
  $options['index'] = false;
else
  exit(ARG_ERR);

#index counter initialization, if any
if ( isset($options['start']) ) {
  if (ctype_digit($options['start'])) {
    $options['start'] = intval($options['start']);
    if ( ($options['index'] != true) || ($options['start'] < 0) )
      exit(ARG_ERR);
  }
  else
    exit(ARG_ERR);
}
else
  $options['start'] = 1;

#############################################################################################
# DECODING START
#############################################################################################

#decode input string
$json = json_decode($ifile);
if ( ! isset($json) )
  exit(FORMAT_ERR);
    if($debug == 'ON') {var_dump($json); echo "\n\n############ END OF DEBUG #############\n";}

#generate an xml header if not prohibited by user
if ( ! isset($options['n']) )
  fwrite($options['of_handler'],XML_HEADER."\n");

#generate a root start tag, if any
if ( isset($options['r']) )
  write('elem_s', $options['r']);

#decide whether the given json is an array or an object
$json_type = gettype ($json);

if ($json_type == 'array')
  print_array($json);
elseif ($json_type == 'object')
  print_object($json);
else
  exit(FORMAT_ERR);

#generate a root end tag, if any
if ( isset($options['r']) )
  write('elem_e', $options['r']);

#end of file