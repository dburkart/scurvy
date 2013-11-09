#!/usr/bin/php
<?php
/*
 *      run-tests.php
 *
 *      Copyright 2010-2013 Dana Burkart <danaburkart@gmail.com>
 *
 */

/*
 * This purpose of this script is to run a series of tests verifying the
 * functionality and completeness of the Scurvy templating engine. To understand
 * whether or not the test has passed, we compare the expected output to the
 * output of actually running scurvy. This script is meant to be run from the
 * command line, and depends on the diff command to function.
 */

require_once '../scurvy.php';


// Runs the specified test and prints a message indicating whether it passed or
// not.
function runTest($test, $cache) {
	$diff = $test($cache);

	if ( !$diff ) {
		echo "ok\n";
	} else {
		echo "Unexpected output:\n $diff\n";
	}
}

// Gets the diff between two files, and returns either the diff or false
// (signifying that the files are the same).
function getDiff($a, $b) {
	$diff = array();

	// Run the diff command, pass in -w, because change in whitespace doesn't
	// _really_ change the validity of our output
	exec("diff -w $a $b", $diff);

	if ( empty($diff) )
		return false;
	else
		return implode( "\n", $diff);
}

function test01($cache) {
	echo "Testing template variables...";

	$tmpl = new Scurvy('01_var.html', './', $cache);
	$tmpl->set('var', 'test01');

	$output = $tmpl->render();

	file_put_contents('01_var.run', $output);

	return getDiff('01_var.out', '01_var.run');
}

function test02($cache) {
	echo "Testing include statements...";

	$tmpl = new Scurvy('02_include.html', './', $cache);
	$tmpl->set('var', 'test02');

	$output = $tmpl->render();

	file_put_contents('02_include.run', $output);

	return getDiff('02_include.out', '02_include.run');
}

function test03($cache) {
	echo "Testing expressions...";

	$tmpl = new Scurvy('03_expr.html', './', $cache);
	$tmpl->set('a', 3);
	$tmpl->set('b', 5);
	$tmpl->set('c', false);

	$output = $tmpl->render();
	file_put_contents('03_expr.run', $output);

	return getDiff('03_expr.out', '03_expr.run');
}

function test04($cache) {
	echo "Testing if statements...";

	$tmpl = new Scurvy('04_if.html', './', $cache);
	$tmpl->set('a', 2);
	$tmpl->set('b', 1);
	$tmpl->set('c', false);
	$tmpl->set('d', 4);

	$output = $tmpl->render();
	file_put_contents('04_if.run', $output);

	return getDiff('04_if.out', '04_if.run');
}

function test05($cache) {
	echo "Testing foreach loops...";

	$tmpl = new Scurvy('05_for.html', './', $cache);
	$tmpl->set('thing', array(
				array( 'var' => 0 ),
				array( 'var' => 1 ),
				array( 'var' => 2 ),
				array( 'var' => 3 ),
				array( 'var' => 4 ),
				array( 'var' => 5 ),
				array( 'var' => 6 )));

	$output = $tmpl->render();
	file_put_contents('05_for.run', $output);

	return getDiff('05_for.out', '05_for.run');
}

function test06($cache) {
	echo "Testing scope...";

	$tmpl = new Scurvy('06_scope.html', './', $cache);
	$tmpl->set('a', array(
				array( 'b' => 'first' ),
				array( 'b' => 'second' ),
				array( 'b' => 'third' ),
				array( 'b' => 'fourth' )));
	$tmpl->set('b', 'rumplestiltskin');

	$output = $tmpl->render();
	file_put_contents('06_scope.run', $output);

	return getDiff('06_scope.out', '06_scope.run');
}

$cache = false;
if (in_array('-cache', $argv)) $cache = true;

// Run our tests.
$time = microtime();
runTest('test01', $cache);
runTest('test02', $cache);
runTest('test03', $cache);
runTest('test04', $cache);
runTest('test05', $cache);
runTest('test06', $cache);
$time = microtime() - $time;

echo "Running time: $time\n";