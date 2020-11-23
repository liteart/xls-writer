<?php
namespace Test;

use PHPUnit_Framework_TestCase;
use Xls\Workbook;

/**
 *
 */
class TestAbstract extends PHPUnit_Framework_TestCase
{
    public const WORKBOOK_TS = 1429042916;

    /**
     * @var Workbook
     */
    protected $workbook;

    /**
     * @var string
     */
    protected $testFilePath;

    /**
     *
     */
    public function setUp()
    {
        $this->testFilePath = TEST_DATA_PATH . '/test.xls';
        $this->workbook = $this->createWorkbook();
    }

    /**
     *
     */
    public function tearDown()
    {
        //@unlink($this->testFilePath);
    }

    /**
     * @param $prefix
     * @param $suffix
     *
     * @return string
     */
    protected function getFilePath($prefix, $suffix = '')
    {
        return TEST_DATA_PATH . '/' . $prefix . $suffix . '.xls';
    }

    /**
     * @return Workbook
     */
    protected function createWorkbook()
    {
        $workbook = new Workbook();
        $workbook->setCreationTimestamp(self::WORKBOOK_TS);

        return $workbook;
    }

    /**
     * @param string $name
     * @param string $suffix
     */
    protected function assertTestFileEqualsTo($name, $suffix = '')
    {
        $this->assertFileExists($this->testFilePath);
        $correctFilePath = $this->getFilePath($name, $suffix);
        $this->assertFileEquals($correctFilePath, $this->testFilePath);
    }
}
