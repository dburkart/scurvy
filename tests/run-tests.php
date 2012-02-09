#!/usr/local/bin/php
<?php
/*
 *      run-tests.php
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

/*
 * This purpose of this script is to run a series of tests verifying the
 * functionality and completeness of the Scurvy templating engine. To understand
 * whether or not the test has passed, we compare the expected output to the
 * output of actually running scurvy. This script is meant to be run from the
 * command line, and depends on the diff command to function.
 */

require_once '../src/scurvy.php';

// Runs the specified test and prints a message indicating whether it passed or
// not.
function runTest($test) {
	$diff = $test();
	
	if ( !$diff ) echo "ok\n";
	else {
		echo "Unexpected output:\n $diff\n";
	}
}

// Gets the diff between two files, and returns either the diff or false 
// (signifying that the files are the same).
function getDiff($a, $b) {
	$diff = array();
	exec("diff $a $b", $diff);
	
	if ( empty($diff) )
		return false;
	else
		return implode( "\n", $diff);
}

function test01() {
	echo "Testing template variables...";

	$tmpl = new Scurvy('01_var.html', './');
	$tmpl->set('var', 'test01');

	$output = $tmpl->render();
	
	file_put_contents('01_var.run', $output);
	
	return getDiff('01_var.out', '01_var.run');
}

function test02() {
	echo "Testing include statements...";
	
	$tmpl = new Scurvy('02_include.html', './');
	$tmpl->set('var', 'test02');
	
	$output = $tmpl->render();
	
	file_put_contents('02_include.run', $output);
	
	return getDiff('02_include.out', '02_include.run');
}

// Run our tests.
runTest('test01');
runTest('test02');

