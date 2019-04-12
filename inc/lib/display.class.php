<?php

class display {
    
    public static function formatDatesWithPrecision($dates, $precisions) {
        $r = array();
        $d = explode(',', $dates);
        $p = explode(',', $precisions);
        for ($i = 0; $i < count($d); $i++) {
            if (!empty($p[$i])) {
                $precision = $p[$i];
            } else {
                $precision = $p[0];
            }
            $r[] = self::formatDateWithPrecision($d[$i], $precision);
        }
        return implode(' ou ', $r);
    }
    
    public static function formatDateWithPrecision($date, $precision = 11) {
        if ($precision == 9) {
            return self::formatDate(substr($date, 0, 4), null, null);
        }
        elseif ($precision == 10) {
            return self::formatDate(substr($date, 0, 4), substr($date, 5, 2), null);
        }
        elseif ($precision == 11) {
            return self::formatDate(substr($date, 0, 4), substr($date, 5, 2), substr($date, 8, 2));
        }
    }
    
    public static function formatDate($year, $month, $day) {
        $r = '';
        if ($year != null) {
            if ($month != null) {
                if ($day != null) {
                    if ($day == 1) {
                        $r .= '{{1er}}';
                    } else {
                        $r .= ltrim($day, '0');
                    }
                    $r .= ' ';
                }
                $months = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
                $r .= $months[$month - 1].' ';
            }
            $r .= $year;
        }
        return $r;
    }
    
    public static function formatInt($value) {
        return number_format($value, 0, ',', ' ');
    }
    
}

?>