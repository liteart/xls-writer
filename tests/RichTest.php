<?php
namespace Test;

use Exception;
use Xls\Format;
use Xls\NumberFormat;
use Xls\Fill;
use Xls\Font;

/**
 *
 */
class RichTest extends TestAbstract
{
    /**
     * @throws Exception
     */
    public function testRich()
    {
        $sheet = $this->workbook->addWorksheet('New PC');

        $headerFormat = $this->getHeaderFormat();
        $sheet->writeRow(0, 0, array('Title', 'Count', 'Price', 'Amount'), $headerFormat);

        $cellFormat = $this->getCellFormat();
        $countFormat = $this->getCountFormat();
        $priceFormat = $this->getPriceFormat();

        $partNames = array('Intel Core i7 2600K', 'ASUS P8P67', 'DDR2-800 8Gb');
        $sheet->writeCol(1, 0, $partNames, $cellFormat);
        $sheet->writeCol(1, 1, array(1, 1, 4), $countFormat);
        $sheet->writeCol(1, 2, array(500, 325, 100.15), $priceFormat);
        //should be written as formulas
        $sheet->writeCol(1, 3, array('=B2*C2', '=B3*C3', '=B4*C4'), $priceFormat);

        $grandFormat = $this->getGrandTotalFormat();
        $this->assertTrue(NumberFormat::isBuiltIn($grandFormat->getNumFormat()));

        $sheet->writeRow(10, 0, array('Total', '', ''), $grandFormat);
        $sheet->mergeCells(10, 0, 10, 2);
        $sheet->writeFormula(10, 3, '=sum(D2:D10)', $this->getOldPriceFormat());

        $sheet->writeRow(11, 0, array('Grand total', '', ''), $grandFormat);
        $sheet->mergeCells(11, 0, 11, 2);
        //should be written as formula
        $sheet->write(11, 3, '=ROUND(D11-D11*0.2, 2)', $grandFormat);

        $sheet->write(11, 4, '20% скидка!', $this->getDiscountFormat());
        $sheet->write(11, 5, 'subscript', $this->getSubscriptFormat());

        $sheet->setColumnWidth(0, 20);
        $sheet->setColumnWidth(3, 15);

        $anotherSheet = $this->workbook->addWorksheet('Лист2');
        $anotherSheet->write(0, 0, 'Тест');

        $this->workbook->save($this->testFilePath);

        $this->assertTestFileEqualsTo('rich');
    }

    /**
     * @return Format
     */
    protected function getHeaderFormat()
    {
        $format = $this->workbook->addFormat();

        $format->getFont()->setBold()->setColor('blue');

        $format->setBorder(Format::BORDER_THIN, 'navy');
        $format->setAlign('center');
        $format->setPattern(Fill::PATTERN_GRAY50);

        //#ccc
        $this->workbook->setCustomColor(12, 204, 204, 204);
        $format->setFgColor(12);

        return $format;
    }

    /**
     * @return Format
     */
    protected function getDiscountFormat()
    {
        $format = $this->workbook->addFormat();

        $format->getFont()
            ->setColor('red')
            ->setSuperScript()
            ->setSize(14);

        $format->setFgColor('white');
        $format->setBgColor('black');

        return $format;
    }

    /**
     * @return Format
     */
    protected function getSubscriptFormat()
    {
        $format = $this->workbook->addFormat();

        $format->getFont()
            ->setSubScript()
            ->setSize(14);

        return $format;
    }

    /**
     * @return Format
     */
    protected function getCellFormat()
    {
        $format = $this->workbook->addFormat();
        $format->getFont()->setBold(false);
        $format->setBorder(Format::BORDER_THIN, 'navy');
        $format->setUnLocked();

        return $format;
    }

    /**
     * @return Format
     */
    protected function getCountFormat()
    {
        $format = $this->workbook->addFormat();
        $format->getFont()->setBold(false);
        $format->setBorder(Format::BORDER_THIN, 'navy');
        $format->setUnLocked();
        $format->setNumFormat(NumberFormat::TYPE_DECIMAL_1);

        return $format;
    }

    /**
     * @return Format
     */
    protected function getGrandTotalFormat()
    {
        $format = $this->workbook->addFormat();

        $format->getFont()
            ->setBold()
            ->setSize(12)
            ->setName('Tahoma')
            ->setUnderline(Font::UNDERLINE_ONCE);

        $format->setBorder(Format::BORDER_THIN, 'navy');

        $format->setNumFormat(NumberFormat::TYPE_CURRENCY_3);

        return $format;
    }

    /**
     * @return Format
     * @throws Exception
     */
    protected function getOldPriceFormat()
    {
        $format = $this->workbook->addFormat(array(
            'numFormat' => '$0.00',
            'textRotation' => 0
        ));

        $format->getFont()
            ->setSize(12)
            ->setStrikeOut()
            ->setOutLine()
            ->setItalic()
            ->setShadow();

        $format->setBorder(Format::BORDER_THIN, 'navy');
        $format->setLocked();
        $format->setTextWrap();

        return $format;
    }

    /**
     * @return Format
     */
    protected function getPriceFormat()
    {
        $format = $this->workbook->addFormat();
        $format->setBorder(Format::BORDER_THIN, 'navy');
        $format->setNumFormat(NumberFormat::TYPE_CURRENCY_3);

        return $format;
    }
}
