<?php
/**
 * @package    Joomla.UnitTest
 *
 * @copyright  Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

JLoader::register('JFolder', JPATH_PLATFORM . '/joomla/filesystem/folder.php');

/**
 * Test class for JFolder.
 * Generated by PHPUnit on 2011-10-26 at 19:32:37.
 *
 * @package     Joomla.UnitTest
 * @subpackage  Event
 * @since       1.7.0
 */
class JFolderTest extends TestCase
{
	/**
	 * Tests the JFolder::delete method with an array as an input
	 *
	 * @return  void
	 *
	 * @expectedException  UnexpectedValueException
	 * @since   1.7.3
	 */
	public function testDeleteArrayPath()
	{
		JFolder::delete(array('/path/to/folder') );
	}

	/**
	 * Test...
	 *
	 * @return void
	 */
	public function testExists()
	{
		$this->assertTrue(
			JFolder::exists(__DIR__)
		);
	}

	/**
	 * Tests the JFolder::files method.
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	public function testFiles()
	{
		// Make sure previous test files are cleaned up
		$this->_cleanupTestFiles();

		// Make some test files and folders
		mkdir(JPath::clean(JPATH_TESTS . '/tmp/test'), 0777, true);
		file_put_contents(JPath::clean(JPATH_TESTS . '/tmp/test/index.html'), 'test');
		file_put_contents(JPath::clean(JPATH_TESTS . '/tmp/test/index.txt'), 'test');
		mkdir(JPath::clean(JPATH_TESTS . '/tmp/test/test'), 0777, true);
		file_put_contents(JPath::clean(JPATH_TESTS . '/tmp/test/test/index.html'), 'test');
		file_put_contents(JPath::clean(JPATH_TESTS . '/tmp/test/test/index.txt'), 'test');

		// Use of realpath to ensure test works for on all platforms
		$result = JFolder::files(JPath::clean(JPATH_TESTS . '/tmp/test'), 'index.*', true, true, array('index.html'));
		$result[0] = realpath($result[0]);
		$result[1] = realpath($result[1]);
		$this->assertEquals(
			array(
				JPath::clean(JPATH_TESTS . '/tmp/test/index.txt'),
				JPath::clean(JPATH_TESTS . '/tmp/test/test/index.txt')
			),
			$result,
			'Line: ' . __LINE__ . ' Should exclude index.html files'
		);

		// Use of realpath to ensure test works for on all platforms
		$result = JFolder::files(JPath::clean(JPATH_TESTS . '/tmp/test'), 'index.html', true, true);
		$result[0] = realpath($result[0]);
		$result[1] = realpath($result[1]);
		$this->assertEquals(
			array(
				JPath::clean(JPATH_TESTS . '/tmp/test/index.html'),
				JPath::clean(JPATH_TESTS . '/tmp/test/test/index.html')
			),
			$result,
			'Line: ' . __LINE__ . ' Should include full path of both index.html files'
		);

		$this->assertEquals(
			array(
				JPath::clean('index.html'),
				JPath::clean('index.html')
			),
			JFolder::files(JPath::clean(JPATH_TESTS . '/tmp/test'), 'index.html', true, false),
			'Line: ' . __LINE__ . ' Should include only file names of both index.html files'
		);

		// Use of realpath to ensure test works for on all platforms
		$result = JFolder::files(JPath::clean(JPATH_TESTS . '/tmp/test'), 'index.html', false, true);
		$result[0] = realpath($result[0]);
		$this->assertEquals(
			array(
				JPath::clean(JPATH_TESTS . '/tmp/test/index.html')
			),
			$result,
			'Line: ' . __LINE__ . ' Non-recursive should only return top folder file full path'
		);

		$this->assertEquals(
			array(
				JPath::clean('index.html')
			),
			JFolder::files(JPath::clean(JPATH_TESTS . '/tmp/test'), 'index.html', false, false),
			'Line: ' . __LINE__ . ' non-recursive should return only file name of top folder file'
		);

		$this->assertFalse(
			JFolder::files('/this/is/not/a/path'),
			'Line: ' . __LINE__ . ' Non-existent path should return false'
		);

		$this->assertEquals(
			array(),
			JFolder::files(JPath::clean(JPATH_TESTS . '/tmp/test'), 'nothing.here', true, true, array(), array()),
			'Line: ' . __LINE__ . ' When nothing matches the filter, should return empty array'
		);

		// Clean up our files
		$this->_cleanupTestFiles();
	}

	/**
	 * Tests the JFolder::folders method.
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	public function testFolders()
	{
		// Make sure previous test files are cleaned up
		$this->_cleanupTestFiles();

		// Create the test folders
		mkdir(JPath::clean(JPATH_TESTS . '/tmp/test'), 0777, true);
		mkdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo1'), 0777, true);
		mkdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo1/bar1'), 0777, true);
		mkdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo1/bar2'), 0777, true);
		mkdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo2'), 0777, true);
		mkdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo2/bar1'), 0777, true);
		mkdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo2/bar2'), 0777, true);

		$this->assertEquals(
			array(),
			JFolder::folders(JPath::clean(JPATH_TESTS . '/tmp/test'), 'bar1', true, true, array('foo1', 'foo2'))
		);

		// Use of realpath to ensure test works for on all platforms
		$result = JFolder::folders(JPath::clean(JPATH_TESTS . '/tmp/test'), 'bar1', true, true, array('foo1'));
		$result[0] = realpath($result[0]);
		$this->assertEquals(
			array(JPath::clean(JPATH_TESTS . '/tmp/test/foo2/bar1')),
			$result
		);

		// Use of realpath to ensure test works for on all platforms
		$result = JFolder::folders(JPath::clean(JPATH_TESTS . '/tmp/test'), 'bar1', true, true);
		$result[0] = realpath($result[0]);
		$result[1] = realpath($result[1]);
		$this->assertEquals(
			array(
				JPath::clean(JPATH_TESTS . '/tmp/test/foo1/bar1'),
				JPath::clean(JPATH_TESTS . '/tmp/test/foo2/bar1'),
			),
			$result
		);

		// Use of realpath to ensure test works for on all platforms
		$result = JFolder::folders(JPath::clean(JPATH_TESTS . '/tmp/test'), 'bar', true, true);
		$result[0] = realpath($result[0]);
		$result[1] = realpath($result[1]);
		$result[2] = realpath($result[2]);
		$result[3] = realpath($result[3]);
		$this->assertEquals(
			array(
				JPath::clean(JPATH_TESTS . '/tmp/test/foo1/bar1'),
				JPath::clean(JPATH_TESTS . '/tmp/test/foo1/bar2'),
				JPath::clean(JPATH_TESTS . '/tmp/test/foo2/bar1'),
				JPath::clean(JPATH_TESTS . '/tmp/test/foo2/bar2'),
			),
			$result
		);

		// Use of realpath to ensure test works for on all platforms
		$result = JFolder::folders(JPath::clean(JPATH_TESTS . '/tmp/test'), '.', true, true);
		$result[0] = realpath($result[0]);
		$result[1] = realpath($result[1]);
		$result[2] = realpath($result[2]);
		$result[3] = realpath($result[3]);
		$result[4] = realpath($result[4]);
		$result[5] = realpath($result[5]);

		$this->assertEquals(
			array(
				JPath::clean(JPATH_TESTS . '/tmp/test/foo1'),
				JPath::clean(JPATH_TESTS . '/tmp/test/foo1/bar1'),
				JPath::clean(JPATH_TESTS . '/tmp/test/foo1/bar2'),
				JPath::clean(JPATH_TESTS . '/tmp/test/foo2'),
				JPath::clean(JPATH_TESTS . '/tmp/test/foo2/bar1'),
				JPath::clean(JPATH_TESTS . '/tmp/test/foo2/bar2'),
			),
			$result
		);

		$this->assertEquals(
			array(
				JPath::clean('bar1'),
				JPath::clean('bar1'),
				JPath::clean('bar2'),
				JPath::clean('bar2'),
				JPath::clean('foo1'),
				JPath::clean('foo2'),
			),
			JFolder::folders(JPath::clean(JPATH_TESTS . '/tmp/test'), '.', true, false)
		);

		// Use of realpath to ensure test works for on all platforms
		$result = JFolder::folders(JPath::clean(JPATH_TESTS . '/tmp/test'), '.', false, true);
		$result[0] = realpath($result[0]);
		$result[1] = realpath($result[1]);

		$this->assertEquals(
			array(
				JPath::clean(JPATH_TESTS . '/tmp/test/foo1'),
				JPath::clean(JPATH_TESTS . '/tmp/test/foo2'),
			),
			$result
		);

		$this->assertEquals(
			array(
				JPath::clean('foo1'),
				JPath::clean('foo2'),
			),
			JFolder::folders(JPath::clean(JPATH_TESTS . '/tmp/test'), '.', false, false, array(), array())
		);

		$this->assertFalse(
			JFolder::folders('this/is/not/a/path'),
			'Line: ' . __LINE__ . ' Non-existent path should return false'
		);

		// Clean up the folders
		rmdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo2/bar2'));
		rmdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo2/bar1'));
		rmdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo2'));
		rmdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo1/bar2'));
		rmdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo1/bar1'));
		rmdir(JPath::clean(JPATH_TESTS . '/tmp/test/foo1'));
		rmdir(JPath::clean(JPATH_TESTS . '/tmp/test'));
	}

	/**
	 * Tests the JFolder::makeSafe method.
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	public function testMakeSafe()
	{
		$actual = JFolder::makeSafe('test1/testdirectory');
		$this->assertEquals('test1/testdirectory', $actual);
	}

	/**
	 * Convenience method to cleanup before and after testFiles
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	private function _cleanupTestFiles()
	{
		$this->_cleanupFile(JPath::clean(JPATH_TESTS . '/tmp/test/test/index.html'));
		$this->_cleanupFile(JPath::clean(JPATH_TESTS . '/tmp/test/test/index.txt'));
		$this->_cleanupFile(JPath::clean(JPATH_TESTS . '/tmp/test/test'));
		$this->_cleanupFile(JPath::clean(JPATH_TESTS . '/tmp/test/index.html'));
		$this->_cleanupFile(JPath::clean(JPATH_TESTS . '/tmp/test/index.txt'));
		$this->_cleanupFile(JPath::clean(JPATH_TESTS . '/tmp/test'));
	}

	/**
	 * Convenience method to clean up for files test
	 *
	 * @param   string  $path  The path to clean
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	private function _cleanupFile($path)
	{
		if (file_exists($path))
		{
			if (is_file($path))
			{
				unlink($path);
			}
			elseif (is_dir($path))
			{
				rmdir($path);
			}
		}
	}
}
