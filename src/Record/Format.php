<?php
namespace Xls\Record;

use Xls\StringUtils;

class Format extends AbstractRecord
{
    public const NAME = 'FORMAT';
    public const ID = 0x041E;

    /**
     * Generate FORMAT record for non "built-in" numerical formats.
     *
     * @param string $format Custom format string
     * @param int $formatIndex   Format index code
     * @return string
     */
    public function getData($format, $formatIndex)
    {
        $data = pack("v", $formatIndex);
        $data .= StringUtils::toBiff8UnicodeLong($format);

        return $this->getFullRecord($data);
    }
}
