<?php

namespace Xls\Record;

class VerticalPagebreaks extends HorizontalPagebreaks
{
    public const NAME = 'VERTICALPAGEBREAKS';
    public const ID = 0x001a;

    // 1000 vertical pagebreaks appears to be an internal Excel 5 limit.
    // It is slightly higher in Excel 97/200, approx. 1026
    public const COUNT_LIMIT = 1000;
}
