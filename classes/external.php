<?php

/**
 * monitor
 *
 * @package monitor
 * @copyright   2016 Uemanet
 * @author      Lucas Vieira
 */

class local_monitor_external extends external_api
{
    private static $day = 60 * 60 * 24;
    private static $session = 20;

    /**
     * Returns default values for get_online_tutors_parameters
     * @return array
     */
    private static function get_online_time_default_parameters()
    {
        return [
            'time_between_clicks' => 60,
            'start_date' => gmdate('d-m-Y', mktime(0, 0, 0, date('m'), date('d') - 7, date('Y'))),
            'end_date' => gmdate('d-m-Y', mktime(0, 0, 0, date('m'), date('d'), date('Y')))
        ];
    }

    /**
     * Returns description of get_online_time parameters
     * @return external_function_parameters
     */
    public static function get_online_time_parameters()
    {
        $default = local_monitor_external::get_online_time_default_parameters();

        return new external_function_parameters(array(
                'time_between_clicks' => new external_value(PARAM_INT, 'Tempo entre os clicks', VALUE_DEFAULT, $default['time_between_clicks']),
                'start_date' => new external_value(PARAM_TEXT, 'Data de início da consulta: dd-mm-YYYY', VALUE_DEFAULT, $default['start_date']),
                'end_date' => new external_value(PARAM_TEXT, 'Data de fim da consulta: dd-mm-YYYY', VALUE_DEFAULT, $default['end_date']),
                'tutor' => new external_value(PARAM_INT, 'ID do Tutor', VALUE_DEFAULT
                )
            )
        );
    }

    public static function get_online_time($timeBetweenClicks, $startDate, $endDate, $tutor)
    {
        global $DB;

        self::validate_parameters(self::get_online_time_parameters(), array(
                'time_between_clicks' => $timeBetweenClicks,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tutor' => $tutor,
            )
        );

        $start = (integer) strtotime($startDate);
        $end   = (integer) strtotime($endDate);

        $interval = $end - $start;
        $days = $interval / local_monitor_external::$day;

        $tutor = $DB->get_record('user', array('id' => $tutor));
        $name  = $tutor->firstname . ' ' . $tutor->lastname;

        for($i = $days; $i != 1; $i--){

            $parameters = array(
                (integer) $tutor,
                $end - local_monitor_external::$day * $i,
                $end - local_monitor_external::$day * ($i - 1)
            );

            // Obter os logs do usuario
            $query = "SELECT id, timecreated
                        FROM {logstore_standard_log}
                        WHERE userid = ?
                        AND timecreated >= ?
                        AND timecreated <= ?
                        ORDER BY userid ASC";

            $result = array();

            try {
                $DB->set_debug(false);
                $logs = $DB->get_records_sql($query, $parameters);
                $date = gmdate("d-m-Y", $end - ( local_monitor_external::$day * $i ));

                $previousLog     = array_shift($logs);
                $previousLogTime = $previousLog->timecreated;
                $sessionStart    = $previousLog->timecreated;
                $onlineTime      = 0;

                foreach ($logs as $log){
                    if(($previousLogTime - $log->timecreated) < $timeBetweenClicks){
                        $onlineTime  += $previousLogTime - $log->timecreated;
                        $sessionStart = $log->timecreated;
                    }

                    $previousLogTime = $log->timecreated;
                }

                $result[$i] = (object) array('name' => $name, 'onlinetime' => $onlineTime, 'date' => $date);
            } catch (\Exception $e){
                throw $e;
            }
        }

        return print_r($result);
    }

    /**
     *
     * Returns description of get_online_time return values
     * @return external_value
     */
    public static function get_online_time_returns()
    {
        return new external_multiple_structure(
          new external_single_structure(
              array(
                  'name' => new external_value(PARAM_TEXT, 'Nome do Tutor'),
                  'onlinetime' => new external_value(PARAM_INT, 'Tempo online'),
                  'date' => new external_value(PARAM_TEXT, 'Data')
              )
          )
        );
    }
}
