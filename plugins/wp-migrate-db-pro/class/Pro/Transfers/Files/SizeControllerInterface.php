<?php

namespace DeliciousBrains\WPMDB\Pro\Transfers\Files;

interface SizeControllerInterface {
    /**
     * Initializes the controller values.
     * @param int $max
     * @param int $fallback
     * @param int $initialSize
     * @param int $retryCount
     *
     * @return void
     */
    public function initialize($max, $fallback, $initialSize = null, $retryCount = 5);

    /**
     * Steps up the payload size to the next value.
     *
     * @return int
     */
    public function step_up_size();

    /**
     * Steps down the payload size to the previous value.
     *
     * @return int
     */
    public function step_down_size($steps = 1);

    /**
     * Returns the current payload size in bytes.
     *
     * @return int
     */
    public function get_current_size();

    /**
     * Returns the fallback size in bytes.
     *
     * @return int
     */
    public function get_fallback_size();

    /**
     * Returns true when the current payload size is at the max calculated size.
     *
     * @return boolean
     */
    public function is_at_max_size();
}
