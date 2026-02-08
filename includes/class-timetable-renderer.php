<?php
/**
 * Timetable Renderer Class
 * Handles rendering the timetable HTML
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GTT_Timetable_Renderer {
    
    /**
     * Render the complete timetable
     */
    public function render() {
        $data = GTT_API_Handler::get_todays_classes();
        
        if (isset($data['error']) && $data['error']) {
            return $this->render_error($data['error']);
        }
        
        $classes = isset($data['result']) ? $data['result'] : array();
        
        ob_start();
        ?>
        <div class="gtt-timetable-container">
            <div class="gtt-header">
                <h1 class="gtt-title">GROUP FITNESS TIMETABLE</h1>
                <div class="gtt-current-time">
                    <?php echo esc_html($this->get_week_range()); ?>
                </div>
            </div>
            
            <div id="gtt-classes-wrapper" class="gtt-classes-wrapper">
                <?php echo $this->render_weekly_grid($classes); ?>
            </div>
            
            <?php if (empty($classes)): ?>
                <div class="gtt-no-classes">
                    <p>No classes scheduled for this week</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $output = ob_get_clean();

        // Remove WP empty paragraphs
        $output = preg_replace('#<p>\s*</p>#', '', $output);

        return $output;
    }
    
    /**
     * Get week range display
     */
    private function get_week_range() {
        $start = new DateTime();
        $start->modify('monday this week');
        $end = clone $start;
        $end->modify('+6 days');
        
        return $start->format('d M Y') . ' to ' . $end->format('d M Y');
    }
    
    /**
     * Render weekly grid view
     */
    private function render_weekly_grid($classes) {
        if (empty($classes)) {
            return '';
        }

        $grouped = $this->group_by_day_and_time($classes);
        $days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');

        $all_times = array();
        foreach ($grouped as $day_classes) {
            foreach ($day_classes as $time => $classes) {
                $all_times[$time] = true;
            }
        }
        ksort($all_times);

        $am_times = array();
        $pm_times = array();

        foreach (array_keys($all_times) as $time) {
            $hour = (int) explode(':', $time)[0];

            if ($hour >= 5 && $hour < 12) {
                $am_times[] = $time;
            } elseif ($hour >= 12 && $hour <= 18) {
                $pm_times[] = $time;
            }
        }

        ob_start();
        ?>

        <div class="gtt-weekly-grid">

            <div class="gtt-grid-header">
                <div class="gtt-time-column-header"></div>
                <?php foreach ($days as $day): ?>
                    <div class="gtt-day-header"><?php echo esc_html($day); ?></div>
                <?php endforeach; ?>
            </div>

            <div class="morning-table">
                <?php echo $this->render_section($am_times, $grouped, $days); ?>
            </div>

            <div class="afternoon-table" style="display:none;">
                <?php echo $this->render_section($pm_times, $grouped, $days); ?>
            </div>

        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Render row
     */
    private function render_section($time_slots, $grouped, $days) {
        if (empty($time_slots)) {
            return '';
        }

        ob_start();
        ?>

        <?php foreach ($time_slots as $time_slot): ?>
            <div class="gtt-grid-row">
                <div class="gtt-time-label">
                    <?php echo esc_html($this->format_time_slot($time_slot)); ?>
                </div>

                <?php foreach ($days as $day): ?>
                    <div class="gtt-day-cell">
                        <?php
                        if (isset($grouped[$day][$time_slot])) {
                            foreach ($grouped[$day][$time_slot] as $class) {
                                echo $this->render_class_card($class);
                            }
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php
        return ob_get_clean();
    }
    
    /**
     * Group classes by day and time slot
     */
    private function group_by_day_and_time($classes) {
        $grouped = array();
        
        foreach ($classes as $class) {
            $day = $class['dayofweek'];

            $time_parts = explode(':', $class['starttime']);
            $hour = (int)$time_parts[0];
            $slot_key = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00';
            
            if (!isset($grouped[$day])) {
                $grouped[$day] = array();
            }
            
            if (!isset($grouped[$day][$slot_key])) {
                $grouped[$day][$slot_key] = array();
            }
            
            $grouped[$day][$slot_key][] = $class;
        }
        
        return $grouped;
    }
    
    /**
     * Render just the classes (for AJAX refresh)
     */
    public function render_classes_only() {
        $data = GTT_API_Handler::get_todays_classes();
        $classes = isset($data['result']) ? $data['result'] : array();
        return $this->render_weekly_grid($classes);
    }
    
    /**
     * Render a single class card
     */
    private function render_class_card($class) {
        $bg_colour = !empty($class['bgcolour']) ? $class['bgcolour'] : '#cccccc';
        $class_name = esc_html($class['name']);
        $trainer_name = esc_html($class['staffname']);
        $start_time = $this->format_time($class['starttime']);
        $end_time = $this->format_time($class['endtime']);
        $location = !empty($class['location']) ? esc_html($class['location']) : '';
        $is_virtual = !empty($class['online_instruction']) || stripos($class_name, 'Virtual') !== false;
        
        ob_start();
        ?>
        <div class="gtt-class-card" style="background-color: <?php echo esc_attr($bg_colour); ?>;">
            <div class="gtt-class-left">
                <div class="gtt-class-name"><?php echo $class_name; ?></div>
            </div>
            <div class="gtt-class-right">
                <?php if ($is_virtual): ?>
                    <div class="gtt-class-meta">Virtual Class</div>
                <?php else: ?>
                    <div class="gtt-class-meta"><?php echo $trainer_name; ?></div>
                <?php endif; ?>
                <div class="gtt-class-time">
                    <?php echo esc_html($start_time . ' - ' . $end_time); ?>
                </div>
                <?php if ($location && !$is_virtual): ?>
                    <div class="gtt-class-location"><?php echo $location; ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Format time slot label
     */
    private function format_time_slot($time_slot) {
        $time_parts = explode(':', $time_slot);
        $hour = (int)$time_parts[0];
        
        if ($hour === 0) {
            return '12:00 AM';
        } elseif ($hour < 12) {
            return $hour . ':00 AM';
        } elseif ($hour === 12) {
            return '12:00 PM';
        } else {
            return ($hour - 12) . ':00 PM';
        }
    }
    
    /**
     * Format time from 24hr to 12hr
     */
    private function format_time($time) {
        $time_parts = explode(':', $time);
        $hour = (int)$time_parts[0];
        $minute = $time_parts[1];
        
        $period = $hour >= 12 ? 'pm' : 'am';
        $display_hour = $hour > 12 ? $hour - 12 : ($hour === 0 ? 12 : $hour);
        
        return $display_hour . ':' . $minute . ' ' . $period;
    }
    
    /**
     * Render error message
     */
    private function render_error($error) {
        ob_start();
        ?>
        <div class="gtt-timetable-container">
            <div class="gtt-error">
                <h2>Unable to load timetable</h2>
                <p><?php echo esc_html($error); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}