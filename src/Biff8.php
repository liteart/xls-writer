<?php

namespace Xls;

class Biff8
{
    /**
     * BIFF8
     *
     * Microsoft Excel 97 (XL8)
     * Microsoft Excel 2000 (XL9)
     * Microsoft Excel 2002 (XL10)
     * Microsoft Office Excel 2003 (XL11)
     * Microsoft Office Excel 2007 (XL12)
     */
    public const VERSION = 0x0600;

    public const MAX_ROWS = 65536;
    public const MAX_ROW_IDX = 65535;
    public const MAX_COLS = 256;
    public const MAX_COL_IDX = 255;

    public const MAX_STR_LENGTH = 255;
    public const MAX_SHEET_NAME_LENGTH = 255;

    public const LIMIT = 8228;

    /*
       8228 : Maximum Excel97 block size
         -4 : Length of block header
         -8 : Length of additional SST header information
         -8 : Arbitrary number to keep within addContinue() limit = 8208
    */
    public const CONTINUE_LIMIT = 8208;

    public const WORKBOOK_NAME = 'Workbook';

    /**
     * The codepage indicates the text encoding used for strings
     */
    public const CODEPAGE = 0x04B0;
}
