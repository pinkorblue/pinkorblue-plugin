<?php
namespace Robera\AB;

if (class_exists('TimeUtils')) {
    return;
}

class TimeUtils
{
    public static function formatTimeDiffMilli($timediff)
    {
        $milli = sprintf("%03d", ($timediff - floor($timediff)) * 1000);
        $time = new \DateTime(date('Y-m-d H:i:s.'.$milli, $timediff));
        $format = 'i:s';
        if ($timediff > 60 * 60) {
            $format = 'H:' . $format;
        }
        return $time->format($format) . "." . $milli;
    }
}
