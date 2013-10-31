<?php
/*
 * algorithms libarary
 * supporing list:
 * luhn
 */
class algo
{
    /*
     * Luhn algorithms
     */
    public static function luhn($input = '')
    {
        if(empty($input))
            return false;
        $digit_arr = array_reverse(str_split($input, 1));
        foreach($digit_arr as $index => $digit)
        {
            if($index % 2 == 0)
            {
                $digit *= 2;
                if($digit > 9)
                {
                    $digit = array_sum(str_split((string)$digit, 1));
                }
            }
            $digit_arr[$index] = $digit;
        }

        return (string)((10 - array_sum($digit_arr)%10)%10);
    }
}

/*
 * test:
 * echo luhn_algo::luhn('7992739871');
 */
