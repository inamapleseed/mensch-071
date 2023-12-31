<?php
namespace OCM\Traits\Front;
trait Range_price {
    private function getPrice($rates, $target_value, $percent_of) {
        $ranges = $rates['ranges'];
        $status = false;
        $in_percent = '';
        $cost = 0;
        $block = 0;
        $end = 0;
        $cumulative = 0;
        $iterative = 0;
        $iterative_cumulative = 0;
        $no_of_blocks = 0;
        $block_value = 0;
        $target_value = round($target_value, 8);
        ITERATE:
        foreach($ranges as $range) {
            $start = $range['start'];
            $end = $range['end'];
            if ($start && !$end) {
                $end = PHP_INT_MAX;
            }
            $cost = $range['percent'] ? ($range['value'] * $percent_of) : $range['value'];
            if ($start <= $target_value && $target_value <= $end) {
                $status = true; 
                $end = $target_value;
                $in_percent = $range['percent'] ? (abs($range['value']) * 100) . '%' : '';
            }
            $block = $range['block'];
            $partial = (int)$range['partial'];
            $fraction = $partial === 1;
            if ($block > 0) {
                //incorrect block seting, reset its value to 1
                if (!$status && $block >= $end) {
                    $block = 1;
                }
                /* round to complete block for iteration purpose. 
                  For flooring, keep current block w/o fraction and for ceiling value, round to next block.
                */
                if (!$fraction) {
                    if(is_float($end) && fmod($end, $block) != 0) {
                        $end = $partial == 2 ? ($end - fmod($end, $block)) : ($end - fmod($end, $block)) + $block;
                    }
                    else if($block >= 1 && ($end % $block) != 0) {
                       $end =  $partial == 2 ? ($end - ($end % $block)) : ($end - ($end % $block)) + $block; 
                    }
                }
                $no_of_blocks = 0;
                if ($start == 0 && !$fraction && $block >= 1) {
                    $start = 1;
                }
                while($start <= $end) {
                    if ($fraction) {
                        $no_of_blocks =  ($end-$start) >= $block ? ($no_of_blocks + 1) : ($no_of_blocks + ($end - $start) / $block);
                    } else {
                        $no_of_blocks++;
                    }
                    $start += $block;
                    //todo optimize, adjust no_of_block when block is less than 1
                    if (!$fraction && $block < 1 && $start > $end) {
                        $no_of_blocks--;
                    }
                }
                $cost = ($no_of_blocks * $cost);
                $block_value += ($no_of_blocks * $block);
            }
            $cumulative += $cost;
            if ($status) break;
        }
        // if not found, lets try repeating if it was set
        if (!$status && !empty($rates['additional']) && $rates['additional']['repeat'] && $target_value > $end) {
            $target_value -= $end;
            $iterative += $cost;
            $iterative_cumulative += $cumulative;
            goto ITERATE;
        }
         /* if not found and additional price was set */
        if (!$status && !empty($rates['additional']) && $rates['additional']['max'] >= $target_value) {
            $additional = $rates['additional']['percent'] ? ($rates['additional']['value'] * $percent_of) : $rates['additional']['value'];
            $additional_per = $rates['additional']['block'];
            while($end < $target_value) {
                $cost += $additional;
                $cumulative += $additional;
                $end += $additional_per;
            }
            $status = true;
        }
        return array(
            'cost'        => $status ? ($cost + $iterative) : 0,
            'cumulative'  => $cumulative + $iterative_cumulative,
            'status'      => $status,
            'block'       => $no_of_blocks,
            'blockValue'  => $block_value,
            'in_percent'  => $in_percent
        );
    }
}