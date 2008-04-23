<?php

/*
 * [S]imple framework
 * 2007-2008 Zame Software Development (http://zame-dev.org)
 * All rights reserved
 *
 * Page class
 */

require_once(S_BASE.'web/template.php');
require_once(S_BASE.'web/control.php');

##
# [PAGE_INIT] Page init event, called before form handling
##
define('PAGE_INIT', 1);

##
# [PAGE_PRE_RENDER] Page pre-render event, called before render
##
define('PAGE_PRE_RENDER', 2);

define('PAGE_FLOW_BREAK', 0);
define('PAGE_FLOW_NORMAL', 1);
define('PAGE_FLOW_ERROR', 2);
define('PAGE_FLOW_REDIRECT', 3);
define('PAGE_FLOW_RENDER', 4);

##
# [CSS] Path to css (for using in templates)
##
define('CSS', ROOT.'css/');

##
# [JS] Path to javascripts (for using in templates)
##
define('JS', ROOT.'js/');

##
# [IMG] Path to images (for using in templates)
##
define('IMG', ROOT.'img/');

##
# .begin
# = class SPage
##
class SPage
{
	##
	# [$vars] Template variables
	##
	var $vars = array();

	var $validators = array();
	var $controls = array();

	##
	# [$template_name] Page template
	##
	var $template_name = '';

	##
	# [$design_page_name] Design template
	##
	var $design_page_name = '';

	##
	# [$error_page_name] Error page template
	##
	var $error_page_name = '';

	##
	# [$content_type] Content-type
	##
	var $content_type = 'text/html';

	var $form_data = array();	// used while render form and form controls

	var $_start_time = 0;
	var $_flow = PAGE_FLOW_NORMAL;
	var $_events = array();
	var $_error_message = '';
	var $_redirect = '';
	var $_headers = array();
	var $_form_posted = '';
	var $_form_action = '';
	var $_uploaded_files = array();

	function SPage()
	{
		$this->__construct();
	}

	##
	# = void __construct()
	# Don't forget to call parent constructor in your page
	##
	function __construct()
	{
		$this->_start_time = get_microtime();

		if (DEBUG)
		{
			dwrite("<b>[Page processing begin]</b>");

			if (conf('page.show_vars', false))
			{
				dwrite_msg('GET', dump_str($_GET));
				dwrite_msg('POST', dump_str($_POST));
				dwrite_msg('SESSION', dump_str($_SESSION));
				dwrite_msg('FILES', dump_str($_FILES));
				dwrite_msg('COOKIE', dump_str($_COOKIE));
			}
		}

		$this->template_name = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $this->script_name() . '.tpl';
		$this->design_page_name = BASE.'templates/design.tpl';
		$this->error_page_name = BASE.'templates/error.tpl';
	}

	##
	# = string script_name()
	# Returns script name (w/o .php extension)
	##
	function script_name()
	{
		return basename($_SERVER['SCRIPT_NAME'], '.php');
	}

	##
	# = void cache_set(string $name, mixed $value)
	# Set value in cache (session keys **"page.<script_name>.<name>"** is used as cache)
	##
	function cache_set($name, $value)
	{
		$arr = _SESSION('page.'.$this->script_name(), array());
		$arr[$name] = $value;
		$_SESSION['page.'.$this->script_name()] = $arr;
	}

	##
	# = void cache_remove(string $name)
	# Remove value from cache
	##
	function cache_remove($name)
	{
		if (InSESSION('page.'.$this->script_name())) {
			if (array_key_exists($name, $_SESSION['page.'.$this->script_name()])) {
				unset($_SESSION['page.'.$this->script_name()][$name]);
			}
		}
	}

	##
	# = mixed cache_get(string $name, mixed $def='')
	# Get value from cache
	##
	function cache_get($name, $def='')
	{
		$arr = _SESSION('page.'.$this->script_name(), array());
		return (array_key_exists($name, $arr) ? $arr[$name] : $def);
	}

	##
	# = void add_validator(string $field, &$validator)
	# [$field] Field name
	# [$validator] Instantiated validator class
	##
	function add_validator($field, &$validator)
	{
		if (!array_key_exists($field, $this->validators)) $this->validators[$field] = array();
		$this->validators[$field][] =& $validator;
	}

	##
	# = void add_validators(string $field, array $arr)
	# [$field] Field name
	# [$arr] Array of validators
	##
	function add_validators($field, $arr)
	{
		foreach ($arr as $k=>$v) {
			$this->add_validator($field, $arr[$k]);
		}
	}

	##
	# = void add_control($name, &$ctl)
	# [$name] Control name
	# [$ctl] Instantiated control class
	##
	function add_control($name, &$ctl)
	{
		if (array_key_exists($name, $this->controls)) {
			if (DEBUG) dwrite("Control '$name' already added");
			return;
		}

		$ctl->page =& $this;
		$this->controls[$name] =& $ctl;
	}

	##
	# = SControl &get_control(string $name)
	# [$name] Control name
	# Returns added control by name
	##
	function &get_control($name)
	{
		if (!array_key_exists($name, $this->controls)) error("Control '$name' not found");
		$ctl =& $this->controls[$name];
		return $ctl;
	}

	##
	# = void add_event(int $type, string $method_name)
	# [$type] Event type (PAGE_INIT or PAGE_PRE_RENDER)
	# [$method_name] Class method, which will be called on event
	##
	function add_event($type, $method_name)
	{
		if (!array_key_exists($type, $this->_events)) $this->_events[$type] = array();
		$this->_events[$type][] = $method_name;
	}

	##
	# = mixed get_var(string $name, mixed $def='')
	##
	function get_var($name, $def='')
	{
		return (array_key_exists($name, $this->vars) ? $this->vars[$name] : $def);
	}

	##
	# = array validation_errors()
	# Returns assoc array of validation errors
	# **key** - field name
	# **$result[$key]** - validation error
	##
	function validation_errors()
	{
		$errors = array();

		foreach ($this->validators as $fld=>$arr)
		{
			foreach ($arr as $vl)
			{
				$vl->page = $this;

				if (!$vl->validate($fld, $this->vars))
				{
					$errors[$fld] = $vl->error_message($fld, $this->vars);
					break;
				}
			}
		}

		return $errors;
	}

	##
	# = void validate()
	# Validate page, and fill **'errors'** template variable with validation errors
	##
	function validate()
	{
		$this->vars['errors'] = $this->validation_errors();
	}

	##
	# = bool is_valid()
	# Returns **true** if page is valid, **false** otherwise
	##
	function is_valid()
	{
		if (!array_key_exists('errors', $this->vars)) $this->validate();
		return (!count($this->get_var('errors', array())));
	}

	##
	# = protected void i_process_post()
	# Internal POST parsing. When value **'_s_<form-name>_action'** exists in post, set **posted form name** and **form action**
	##
	function i_process_post()
	{
		foreach ($_POST as $k=>$v)
		{
			if (substr($k, 0, 3)=='_s_' && substr($k, -7)=='_action') {
				$this->_form_posted = substr($k, 3, -7);
				$this->_form_action = $v;
			} else {
				$this->vars[$k] = $v;
			}
		}

		foreach ($_FILES as $k=>$v)
		{
			if ($v['error'] == UPLOAD_ERR_OK)
			{
				if (is_uploaded_file($v['tmp_name']))
				{
					if ($v['size'] != 0)
					{
						$this->_uploaded_files[$k] = UPLOAD_ERR_OK;
						$this->vars[$k] = '_uploaded_file_';
						$this->vars[$k.':name'] = $v['name'];
						$this->vars[$k.':type'] = $v['type'];
						$this->vars[$k.':size'] = $v['size'];
						$this->vars[$k.':tmp_name'] = $v['tmp_name'];
					}
					else { $this->_uploaded_files[$k] = UPLOAD_ERR_NO_FILE; }
				}
				else { $this->_uploaded_files[$k] = UPLOAD_ERR_PARTIAL; }
			}
			elseif ($v["error"] != UPLOAD_ERR_NO_FILE) {
				$this->_uploaded_files[$k] = $v['error'];
			}
		}
	}

	##
	# = bool file_is_uploaded(string $name)
	# [$name] Field name
	# Returns **true** is file is uploaded without errors, **false** otherwise
	##
	function file_is_uploaded($name)
	{
		if (!array_key_exists($name, $this->_uploaded_files)) return false;
		return ($this->_uploaded_files[$name] == UPLOAD_ERR_OK);
	}

	##
	# = void move_upl_file(string $name, string $destination_path)
	# [$name] Field name
	# [$destination_path] Destination path
	##
	function move_upl_file($name, $destination_path)
	{
		if (!$this->file_is_uploaded($name)) {
			if (DEBUG) dwrite("Can't move uploaded file for field '$name' because this file not uploaded");
			return;
		}

		move_uploaded_file($this->vars[$name.':tmp_name'], $destination_path);
	}

	##
	# = bool is_post_back(string $form_name='')
	# Returns **true** is postback occurred (when form specified, also checks submitted form name), **false** otherwise
	##
	function is_post_back($form_name='')
	{
		if (!strlen($form_name)) return strlen($this->_form_posted);
		return ($this->_form_posted == $form_name);
	}

	##
	# = void set_select_data(string $name, array $data, string $group='__default__')
	# [$name] Select control id
	# [$data] Select items, key => item id, value => value
	# [$group] Items group
	##
	function set_select_data($name, $data, $group='__default__')
	{
		$sd = $this->get_var($name.':data', array());
		$sd[$group] = $data;
		$this->vars[$name.':data'] = $sd;
	}

	##
	# = bool check_select_items(string $name, array $data)
	# [$name] Select control id
	# [$data] Select items
	# Returns **true** when submitted value exists in **$data**, **false** otherwise
	##
	function check_select_items($name, $data)
	{
		if (!array_key_exists($name, $this->vars)) return false;

		if (is_array($this->vars[$name]))
		{
			foreach ($this->vars[$name] as $val) {
				if (!array_key_exists($val, $data)) return false;
			}

			return true;
		}
		else { return array_key_exists($this->vars[$name], $data); }
	}

	##
	# = void validate_select_items(string $name, array $data)
	# [$name] Select control id
	# [$data] Select items
	# Throws error, when submitted value not exists in **$data**
	##
	function validate_select_items($name, $data) {
		if (!$this->check_select_items($name, $data)) error('Please stop hack us, evil haxor.');
	}

	##
	# = void add_header(string $name, string $value)
	##
	function add_header($name, $value)
	{
		foreach ($this->_headers as $k=>$v) {
			if (strtolower($k) == strtolower($name)) {
				$this->_headers[$k] = $value;
				return;
			}
		}

		$this->_headers[$name] = $value;
	}

	function i_init()
	{
		$this->i_process_post();

		if (!array_key_exists(PAGE_INIT, $this->_events)) return;

		foreach ($this->_events[PAGE_INIT] as $method) {
			call_user_func(array(&$this, $method));
			if ($this->_flow != PAGE_FLOW_NORMAL) return;
		}
	}

	##
	# = protected void i_handle_forms()
	# Internal form handling, call **'on_<posted-form-name>_submit'** method (in page or in controls) when form submitted
	##
	function i_handle_forms()
	{
		if (!strlen($this->_form_posted)) return;
		$method = 'on_'.$this->_form_posted.'_submit';

		if (method_exists($this, $method))
		{
			call_user_func(array(&$this, $method), $this->_form_action);
			return;
		}

		foreach ($this->controls as $k=>$v)
		{
			$ctl =& $this->controls[$k];

			if (method_exists($ctl, $method))
			{
				$ctl->page =& $this;
				call_user_func(array(&$ctl, $method), $this->_form_action);
				return;
			}
		}

		if (DEBUG) dwrite("Method '$method' not defined");
	}

	function i_pre_render()
	{
		if (!array_key_exists(PAGE_PRE_RENDER, $this->_events)) return;

		foreach ($this->_events[PAGE_PRE_RENDER] as $method) {
			call_user_func(array(&$this, $method));
			if ($this->_flow != PAGE_FLOW_NORMAL) return;
		}
	}

	##
	# = void output_headers()
	# Output headers to browser. In most of cases, you don't need to call this method directly
	##
	function output_headers()
	{
		$this->add_header('Content-type', $this->content_type);
		foreach ($this->_headers as $k=>$v) header($k.': '.$v);
	}

	##
	# = void output_result(string $res)
	# Output result to browser. In most of cases, you don't need to call this method directly
	##
	function output_result($res)
	{
		global $s_runconf;
		$nw = get_microtime();

		$this->output_headers();
		echo $res;

		if  ($this->content_type=='text/html' && DEBUG)
		{
			dwrite('<b>[Page processing end]</b>');
			dwrite('Page processing takes: ' . number_format(($nw - $this->_start_time), 8));
			dwrite('SQL parsing takes: ' . number_format($s_runconf->get('time.sql.parse'), 8));
			dwrite('SQL queries takes: ' . number_format($s_runconf->get('time.sql.query'), 8));
			dwrite('Templates takes: ' . number_format($s_runconf->get('time.template'), 8) . ' (approx)');
			dflush();
		}
	}

	##
	# = string render_result()
	# Render page to string. In most of cases, you don't need to call this method directly
	##
	function render_result()
	{
		$tpl =& new STemplate();
		$tpl->vars =& $this->vars;
		$tpl->controls =& $this->controls;

		$res = $tpl->process($this->template_name);

		if (strlen($this->design_page_name))
		{

			$tpl =& new STemplate();
			$tpl->vars =& $this->vars;
			$tpl->controls =& $this->controls;

			$tpl->vars['__content__'] = $res;
			$res = $tpl->process($this->design_page_name);

		}

		return $res;
	}

	function render()
	{
		$this->output_result($this->render_result());
		/* ob_flush(); */
	}

	function error_handler()
	{
		$tpl =& new STemplate();
		$tpl->vars =& $this->vars;
		$tpl->controls =& $this->controls;

		$tpl->vars['__error__'] = $this->_error_message;

		$res = $tpl->process($this->error_page_name);
		$this->output_result($res);
	}

	function redirect_handler()
	{
		header('location: ' . $this->_redirect);
		echo ' ';
	}

	function process_flow()
	{
		switch ($this->_flow)
		{
			case PAGE_FLOW_ERROR: $this->error_handler(); break;
			case PAGE_FLOW_REDIRECT: $this->redirect_handler(); break;
			case PAGE_FLOW_RENDER: $this->render(); break;
		}
	}

	##
	# = void error(string $msg)
	# [$msg] Error message
	# Render error page with error message, instead of normal page flow
	##
	function error($msg)
	{
		$this->_error_message = $msg;
		$this->_flow = PAGE_FLOW_ERROR;
	}

	##
	# = void redirect(string $url)
	# [$url] Redirect location
	# Redirect to new location, instead of normal page flow
	##
	function redirect($url)
	{
		$this->_redirect = $url;
		$this->_flow = PAGE_FLOW_REDIRECT;
	}

	##
	# = void process()
	# Call this function to process page
	##
	function process()
	{
		$this->i_init();
		if ($this->_flow != PAGE_FLOW_NORMAL) {$this->process_flow(); return;}

		$this->i_handle_forms();
		if ($this->_flow != PAGE_FLOW_NORMAL) {$this->process_flow(); return;}

		$this->i_pre_render();
		if ($this->_flow != PAGE_FLOW_NORMAL) {$this->process_flow(); return;}

		$this->render();
	}
}
##
# .end
##

?>