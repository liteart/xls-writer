<?php
namespace Xls\Record;

use Xls\StringUtils;
use Xls\Worksheet;

class Sheet extends AbstractRecord
{
    public const NAME = 'SHEET';
    public const ID = 0x0085;

    /**
     * Generate BOUNDSHEET record.
     *
     * @param string $sheetName Worksheet name
     * @param int $offset Location of worksheet BOF
     * @return string
     */
    public function getData($sheetName, $offset = 0)
    {
        $sheetState = Worksheet::STATE_VISIBLE;
        $sheetType = Worksheet::TYPE_SHEET;

        $data = pack("VCC", $offset, $sheetState, $sheetType);
        $data .= StringUtils::toBiff8UnicodeShort($sheetName);

        return $this->getFullRecord($data);
    }
}
