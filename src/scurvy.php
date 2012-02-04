<?php
/*
 *      scurvy.php
 *      
 *      Copyright 2010 Dana Burkart <danaburkart@gmail.com>
 *      
 *      This program is free software; you can redistribute it and/or modify
 *      it under the terms of the GNU General Public License as published by
 *      the Free Software Foundation; either version 2 of the License, or
 *      (at your option) any later version.
 *      
 *      This program is distributed in the hope that it will be useful,
 *      but WITHOUT ANY WARRANTY; without even the implied warranty of
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *      GNU General Public License for more details.
 *      
 *      You should have received a copy of the GNU General Public License
 *      along with this program; if not, write to the Free Software
 *      Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 *      MA 02110-1301, USA.
 */

/**
 * This is a dead-simple templating class. Basically, it can handle variables,
 * includese, loops, and if statements. Here is the syntax:
 * 		{variable}
 *		{include somefile.html}
 * 		{if var}
 * 		...
 * 		{/if}
 * 		{foreach assocArr}
 * 		...
 * 		{/foreach}
 *
 * Known limitations:
 * 		- For if statements and for-loops, do not put multiple tags on the
 * 		  same line, i.e. {if var}...{/if} or {if}{if}...{/if}..., this breaks
 * 		  everything
 *
 * \author Dana Burkart
 */		
class Scurvy {
	private $RE_VAR = '/\{([a-zA-Z0-9_]+)\}/';
	private $RE_INC = '/\{include\s([a-zA-Z0-9_.\/]+)\}/';
	private $RE_FOR_BEG = '/\{foreach\s([a-zA-Z0-9_]+)\}/';
	private $RE_FOR_END = '/\{\/foreach\}/';
	private $RE_IF_BEG = '/\{if\s([a-zA-Z0-9_]+)\}/';
	private $RE_IF_END = '/\{\/if\}/';

	private $name;

	//-- Array containing the strings making up this template
	private $strings;

	//-- Entities in this template, should be self-explanatory
	private $vars;
	private $incTemplates;
	private $forTemplates;
	private $ifTemplates;
	
	private $template_dir;
	
	private $instances;
	
	public function __construct($fuzzyType, $template_dir, $name = 'template') {
		if (is_array($fuzzyType)) {
			$this->strings = $fuzzyType;
			$this->name = $name;
		} else if (file_exists($template_dir.$fuzzyType)) {
			$this->strings = file($template_dir.$fuzzyType);
			$this->name = basename($fuzzyType);
		} else
			die("template: $fuzzyType is not a valid file or array");
		
		$this->instances = new frameworkInstances($template_dir);
		
		$this->template_dir = $template_dir;
		$this->vars = array();
		$this->forTemplates = array();
		$this->ifTemplates = array();
		$this->incTemplates = array();
		$this->parse();
	}

	public function render() {
		$strings = implode($this->strings);
		
		foreach ($this->incTemplates as $path => $include) {
			$incOutput = $include->render();
			$path = preg_replace("/\//", "\/", $path);
			$strings = preg_replace("/\{include\s$path\}/", $incOutput, $strings);
		}

		foreach ($this->ifTemplates as $key => $sub) {
			foreach ($sub as $iKey => $instance) {
				$str = '';
				if (isset($this->vars[$key]) && $this->vars[$key] == true)
					$str = $instance->render();
					
				$strings = preg_replace("/\{if:$key:$iKey\}/", $str, $strings);
			}
		}
		
		foreach ($this->forTemplates as $key => $sub) {
			foreach ($sub as $iKey => $instance) {
				$tmplGen = '';
				if (isset($this->vars[$key]) && is_array($this->vars[$key])) {
					foreach ($this->vars[$key] as $assocArray) {
						foreach ($assocArray as $k => $v)
							$instance->set($k, $v);
						$tmplGen .= $instance->render();
					}
				}
				$strings = preg_replace("/\{for:$key:$iKey\}/", $tmplGen, $strings);
			}
		}
		
		foreach ($this->vars as $key => $val) {
			if (!is_array($val))
				$strings = preg_replace("/\{$key\}/", $val, $strings);
		}
		
		$strings = preg_replace("/\\\{/", '{', $strings);
		$strings = preg_replace("/\\\}/", '}', $strings);

		return $strings;
	}

	public function set($var, $val) {
		//if (is_array($val) && !isset($this->forTemplates[$var]))
			//-- Replace with more robust error handling
			//die("'$var' does not accept arrays");
		
		$this->vars[$var] = $val;
		
		foreach($this->forTemplates as $templateBunch)
			foreach($templateBunch as $sub)
				$sub->set($var, $val);

		foreach($this->ifTemplates as $templateBunch)
			foreach($templateBunch as $sub)
				$sub->set($var, $val);
		
		foreach($this->incTemplates as $sub)
			$sub->set($var, $val);
	}

	//--- Private functions --------------------------------------------------//
	
	private function parse() {
		$count = count($this->strings);
		for ($i = 0; $i < $count; $i++) {
			$matches;
			preg_match_all($this->RE_VAR, $this->strings[$i], $matches);
			if (!empty($matches[0])) {
				foreach ($matches[1] as $match) {
					if (!isset($this->vars[$match]))
						$this->vars[$match] = '';
				}
			}

			$n = preg_match($this->RE_FOR_BEG, $this->strings[$i], $matches);
			if ($n > 0) {
				$forName = $matches[1];
				if (!isset($this->forTemplates[$forName]))
					$this->forTemplates[$forName] = array();
				$n = count($this->forTemplates[$forName]);
				$this->forTemplates[$forName][$n] = $this->parseRecursive($i,
					array($this->RE_FOR_BEG, $this->RE_FOR_END),
					"{$this->name}_for_{$forName}_$n");
				$this->strings[$i] = '{for:'.$forName.':'.$n.'}';
			}

			$n  = preg_match($this->RE_IF_BEG, $this->strings[$i], $matches);
			if ($n > 0) {
				$ifName = $matches[1];
				if (!isset($this->ifTemplates[$ifName]))
					$this->ifTemplates[$ifName] = array();
				$n = count($this->ifTemplates[$ifName]);
				$this->ifTemplates[$ifName][$n] = $this->parseRecursive($i,
					array($this->RE_IF_BEG, $this->RE_IF_END),
					"{$this->name}_if_{$ifName}_$n");
				$this->strings[$i] = '{if:'.$ifName.':'.$n.'}';
			}
			
			preg_match_all($this->RE_INC, $this->strings[$i], $matches);
			if (!empty($matches[0])) {
				foreach($matches[1] as $match) {
					if (!isset($this->incTemplates[$match])) {
						$info = pathinfo($match);
						if ($info['extension'] = 'php') {
							ob_start();
							$this->require_file($match);
							$output = array(ob_get_clean());
							$this->incTemplates[$match] = new Scurvy($output, $this->template_dir);
						} else {
							$this->incTemplates[$match] = new Scurvy($match, $this->template_dir);
						}
					}
				}
			}
		}
	}

	private function parseRecursive($start, $regex, $subName = 'template') {
		$re_beg = $regex[0];
		$re_end = $regex[1];

		$numLines = count($this->strings);
		$i = $start + 1;
		$n = 1;
		
		for ($i; $i < $numLines; $i++) {
			$match = preg_match($re_beg, $this->strings[$i]);

			if ($match) $n += 1;
			else {
				$match = preg_match($re_end, $this->strings[$i]);
				if ($match) {
					$n -= 1;

					if ($n == 0) break;
				}
			}
		}

		if ($n > 0) {
			echo "Opening brace on line $line of {$this->name} has no closing brace\n<br />";
		}

		$subTmpl = array_slice($this->strings, $start + 1, $i - ($start + 1));

		for ($j = $start + 1; $j <= $i; $j++) {
			//-- Just set the string to empty. Don't worry about removing it
			//-- from the array
			$this->strings[$j] = '';
		}
		
		return new Scurvy($subTmpl, $this->template_dir, $subName);
	}
	
	private function require_file($file) {
		if (file_exists($this->template_dir . $file)) {
			require_once $this->template_dir . $file;
		} else {
			$location = $this->instances->findTemplate($file);
			require_once $location . $file;
		}
	}
}

?>
