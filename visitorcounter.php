<?php
// +-----------------------------------------------------------------------+
// | Copyright (c) 2007, Vitaly Doroshko                                   |
// | All rights reserved.                                                  |
// |                                                                       |
// | Redistribution and use in source and binary forms, with or without    |
// | modification, are permitted provided that the following conditions    |
// | are met:                                                              |
// |                                                                       |
// | o Redistributions of source code must retain the above copyright      |
// |   notice, this list of conditions and the following disclaimer.       |
// | o Redistributions in binary form must reproduce the above copyright   |
// |   notice, this list of conditions and the following disclaimer in the |
// |   documentation and/or other materials provided with the distribution.|
// | o The names of the authors may not be used to endorse or promote      |
// |   products derived from this software without specific prior written  |
// |   permission.                                                         |
// |                                                                       |
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS   |
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT     |
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR |
// | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT  |
// | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, |
// | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT      |
// | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, |
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY |
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT   |
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE |
// | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.  |
// |                                                                       |
// +-----------------------------------------------------------------------+
// | Author: Vitaly Doroshko <vdoroshko@mail.ru>                           |
// +-----------------------------------------------------------------------+
//
// $Id$

// {{{ constants and globals

/**
 * @global integer     $GLOBALS["VisitorCounter_reportPeriod"]
 * @name   $VisitorCounter_reportPeriod
 */
$GLOBALS["VisitorCounter_reportPeriod"] = 1;

$GLOBALS["VisitorCounter_hostname"] = "localhost";
$GLOBALS["VisitorCounter_username"] = "root";
$GLOBALS["VisitorCounter_password"] = "c0ff33";

/**
 * @global string      $GLOBALS["VisitorCounter_database"]
 * @name   $VisitorCounter_database
 */
$GLOBALS["VisitorCounter_database"] = "test";

/**
 * @global string      $visitorHits_TableName
 * @name   $visitorHits_TableName
 */
$visitorHits_TableName = "visitor_hits";

/**
 * @global string      $visitorReports_TableName
 * @name   $visitorReports_TableName
 */
$visitorReports_TableName = "visitor_reports";

// }}}
// {{{ functions
// {{{ VisitorCounter_registerHit()

/**
 * @author   Vitaly Doroshko <vdoroshko@mail.ru>
 * @category Logging
 * @param    boolean   $isAuthenticated
 * @return   boolean
 * @version  1.0.0
 */
function VisitorCounter_registerHit($isAuthenticated = false)
{
    global $visitorHits_TableName;

    if (empty($_SERVER["REMOTE_ADDR"])) {
        return false;
    }

    $queryString = sprintf("%s%s%s", $_SERVER["SCRIPT_NAME"],
                           empty($_SERVER["QUERY_STRING"]) ? "" : "?",
                           empty($_SERVER["QUERY_STRING"]) ? "" : $_SERVER["QUERY_STRING"]);

    $query = sprintf("INSERT INTO `%s`.`%s`(`hit_datetime`, `ip_address`, `uri`, `method`, `is_auth`) VALUES(CURRENT_TIMESTAMP, INET_ATON('%s'), '%s', '%s', %s)",
                     $GLOBALS["VisitorCounter_database"],
                     $visitorHits_TableName,
                     mysql_real_escape_string($_SERVER["REMOTE_ADDR"]),
                     mysql_real_escape_string($queryString),
                     mysql_real_escape_string($_SERVER["REQUEST_METHOD"]),
                     (boolean)$isAuthenticated ? "TRUE" : "FALSE");

    $result = mysql_query($query);
    if (false === $result) {
        return false;
    }

    return true;
}

// }}}
// {{{ VisitorCounter_calculateHits()

/**
 * @author   Vitaly Doroshko <vdoroshko@mail.ru>
 * @category Logging
 * @return   boolean
 * @version  1.0.0
 */
function VisitorCounter_calculateHits()
{
    global $visitorHits_TableName;
    global $visitorReports_TableName;

    $query = sprintf("SELECT `report_id`, `report_datetime`, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL %d MINUTE) > `report_datetime` AS `do_report` FROM `%s`.`%s` ORDER BY `report_id` DESC LIMIT 1",
                     $GLOBALS["VisitorCounter_reportPeriod"],
                     $GLOBALS["VisitorCounter_database"],
                     $visitorReports_TableName);

    $result = mysql_query($query);
    if (false === $result) {
        return false;
    }

    $reportData = mysql_fetch_assoc($result);
    if ($reportData) {
        $doReport = (boolean)$reportData["do_report"];
    } else {
        if (mysql_num_rows($result)) {
            $doReport = (boolean)$reportData["do_report"];
        } else {
            $doReport = true;
        }
    }

    if ($doReport) {
        $numGETHits  = 0;
        $numPOSTHits = 0;

        /**
         * Calculating number of GET and POST hits since last calculation period.
         */
        $query = sprintf("SELECT `method`, COUNT(*) AS `num_hits` FROM `%s`.`%s` WHERE `hit_datetime` BETWEEN '%s' AND CURRENT_TIMESTAMP GROUP BY `method`",
                         $GLOBALS["VisitorCounter_database"],
                         $visitorHits_TableName,
                         $reportData["report_datetime"]);

        $result = mysql_query($query);
        if (false === $result) {
            return false;
        }

        $hitsData = mysql_fetch_assoc($result);
        if ($hitsData) {
            if ("GET" == $hitsData["method"]) {
                $numGETHits  = $hitsData["num_hits"];
            } else {
                $numPOSTHits = $hitsData["num_hits"];
            }

            $hitsData = mysql_fetch_assoc($result);
            if ($hitsData) {
                if ("GET" == $hitsData["method"]) {
                    $numGETHits  = $hitsData["num_hits"];
                } else {
                    $numPOSTHits = $hitsData["num_hits"];
                }
            }
        }

        mysql_free_result($result);

        $numAnonymousVisitors     = 0;
        $numAuthenticatedVisitors = 0;

        /**
         * Calculating number of visitors since last calculation period.
         */
        $query = sprintf("SELECT `is_auth`, COUNT(DISTINCT `ip_address`) AS `num_visitors` FROM `%s`.`%s` WHERE `hit_datetime` BETWEEN '%s' AND CURRENT_TIMESTAMP GROUP BY `is_auth`",
                         $GLOBALS["VisitorCounter_database"],
                         $visitorHits_TableName,
                         $reportData["report_datetime"]);

        $result = mysql_query($query);
        if (false === $result) {
            return false;
        }

        $visitorsData = mysql_fetch_assoc($result);
        if ($visitorsData) {
            if (false == (boolean)$visitorsData["is_auth"]) {
                $numAnonymousVisitors = $visitorsData["num_visitors"];
            } else {
                $numAuthenticatedVisitors = $visitorsData["num_visitors"];
            }

            $visitorsData = mysql_fetch_assoc($result);
            if ($visitorsData) {
                if (false == (boolean)$visitorsData["is_auth"]) {
                    $numAnonymousVisitors = $visitorsData["num_visitors"];
                } else {
                    $numAuthenticatedVisitors = $visitorsData["num_visitors"];
                }
            }
        }

        mysql_free_result($result);

        $query = sprintf("INSERT INTO `%s`.`%s`(`report_datetime`, `num_get_hits`, `num_post_hits`, `num_anon_visitors`, `num_auth_visitors`) VALUES(CURRENT_TIMESTAMP, %d, %d, %d, %d)",
                         $GLOBALS["VisitorCounter_database"],
                         $visitorReports_TableName,
                         $numGETHits,
                         $numPOSTHits,
                         $numAnonymousVisitors,
                         $numAuthenticatedVisitors);

        $result = mysql_query($query);
        if (false === $result) {
            return false;
        }
    }

    return true;
}

// }}}
// {{{ VisitorCounter_getLastReportData()

/**
 * @author   Vitaly Doroshko <vdoroshko@mail.ru>
 * @category Logging
 * @return   mixed
 * @version  1.0.0
 */
function VisitorCounter_getLastReportData()
{
    global $visitorReports_TableName;

    $query = sprintf("SELECT * FROM `%s`.`%s` ORDER BY `report_id` DESC LIMIT 1",
                     $GLOBALS["VisitorCounter_database"],
                     $visitorReports_TableName);

    $result = mysql_query($query);
    if (false === $result) {
        return false;
    }

    return (array)mysql_fetch_assoc($result);
}

// }}}
// }}}

$link = mysql_connect($GLOBALS["VisitorCounter_hostname"],
                      $GLOBALS["VisitorCounter_username"],
                      $GLOBALS["VisitorCounter_password"]);
if (!$link) die('Could not connect: ' . mysql_error());

VisitorCounter_calculateHits();
VisitorCounter_registerHit();

mysql_close($link);

?>
