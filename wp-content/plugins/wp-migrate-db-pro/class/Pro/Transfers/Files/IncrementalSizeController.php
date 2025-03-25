<?php

namespace DeliciousBrains\WPMDB\Pro\Transfers\Files;

class IncrementalSizeController implements SizeControllerInterface {

    /**
     * Current segment index.
     *
     * @var int
     */
    private $currentSegment;

    /**
     * Max payload size value in bytes.
     *
     * @var int
     */
    private $max;

    /**
     * The default fallback payload size in bytes.
     * @var int
     */
    private $fallback;

    /**
     * Array of byte values that represent various payload sizes.
     *
     * @var array
     */
    private $seekArray;

    /**
     * Failure retry count.
     *
     * @var int
     */
    private $retry;


    /**
     * Initializes the default values for all the required properties.
     *
     * @param int $max
     * @param int $fallback
     * @param int $initialSize
     * @param int $retryCount
     *
     * @return void
     */
    public function initialize( $max, $fallback, $initialSize = null, $retryCount = 5 ) {
        $this->currentSegment = 0;
        $this->max            = $max;
        $this->fallback       = $fallback;
        $this->retry          = $retryCount;

        $this->initialize_seek_array($initialSize);
    }


    /**
     * increments in the segment size if the seek array has further segments
     * and returns the actual segment size.
     *
     * @return int
     */
    public function step_up_size() {
        if(array_key_exists($this->currentSegment + 1, $this->seekArray)) {
            ++ $this->currentSegment;
        }

        return $this->get_current_size();
    }


    /**
     * Decrements the segment size if the seek array has lower values,
     * or defaults to 0 if it doesn't, and  returns the actual segment size.
     *
     * @param int $steps
     *
     * @return int
     */
    public function step_down_size($steps = 1) {
        if(array_key_exists($this->currentSegment - 1, $this->seekArray)) {
            $newSegment = $this->currentSegment - $steps;
            if(array_key_exists($newSegment, $this->seekArray)) {
                $this->currentSegment = $newSegment;
            } else {
                $this->currentSegment = 0;
            }
        }

        return $this->get_current_size();
    }


    /**
     * Returns current segment's actual size in bytes.
     *
     * @return int
     */
    public function get_current_size() {
        return $this->seekArray[$this->currentSegment];
    }


    /**
     * Returns the fallback size in bytes.
     *
     * @return int
     */
    public function get_fallback_size() {
        return $this->fallback;
    }


    /**
     * Initializes a seek array with different segments values, these sizes are used to set up and step down
     * the payload size during HP mode migration.
     *
     * The first element of the seek array is the fallback size, then every other element is 20% larger of its
     * predecessor. The last element of the array is the $max value.
     *
     * @param int $initialSize
     * @return void
     */
    private function initialize_seek_array($initialSize = null) {
        $this->seekArray = [ $this->fallback ];
        $target          = 0;
        $i               = 0;
        while ( $target < $this->max ) {

            $last_entry = end( $this->seekArray );
            $new_value  = (int)round(( ( $last_entry / 100 ) * 20 ) + $last_entry);

            if ( $new_value > $this->max ) {
                $new_value = $this->max;
            }

            $target = $new_value;
            $i ++;

            $this->seekArray[] = $new_value;
            if ( $new_value === $initialSize ) {
                $this->currentSegment = $i;
            }

            if ( $initialSize === null ) {
                $this->currentSegment = 1;
            }
        }
    }


    /**
     * Returns true of the current segment size equals the max payload size.
     *
     * @return bool
     */
    public function is_at_max_size() {
        return $this->currentSegment === count($this->seekArray) - 1;
    }
}
