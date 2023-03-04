--
-- Visitor Counter Script MySQL 5.0.32 Database Schema.
-- Copyright (c) 2007 Vitaly Doroshko.
-- $Id$
--

--
-- All data about hits are collected in 'visitor_hits' table. Every
-- record contains IP address of the visitor that hit belongs to,
-- URI of the page requested and some other information.
--

DROP TABLE IF EXISTS visitor_hits;
CREATE TABLE visitor_hits(
  -- Hit ID.
  hit_id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
  -- Date and time of the hit.
  hit_datetime DATETIME NOT NULL,
  -- IP address of the visitor.
  ip_address INTEGER NOT NULL DEFAULT 0,
  -- URI of the page requested.
  uri VARCHAR(255) NOT NULL DEFAULT '',
  -- HTTP method is used to request the page.
  method ENUM('GET', 'POST') NOT NULL DEFAULT 'GET',
  -- It has TRUE value if this hit belongs to authenticated visitor.
  is_auth BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE INDEX hit_datetime_ind ON visitor_hits(hit_datetime);
CREATE INDEX ip_address_ind ON visitor_hits(ip_address);

--
-- The information about number of hits and number of unique visitors
-- are collected in 'visitor_reports' table. Every record contains
-- visitor information since last calculation period (see PHP source).
--

DROP TABLE IF EXISTS visitor_reports;
CREATE TABLE visitor_reports(
  -- Report ID.
  report_id INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
  -- Date and time when information has been summarized and stored.
  report_datetime DATETIME NOT NULL,
  -- Number of GET hits since last calculation period.
  num_get_hits INTEGER NOT NULL DEFAULT 0,
  -- Number of POST hits since last calculation period.
  num_post_hits INTEGER NOT NULL DEFAULT 0,
  -- Number of anonymous visitors since last calculation period.
  num_anon_visitors INTEGER NOT NULL DEFAULT 0,
  -- Number of authenticated visitors since last calculation period.
  num_auth_visitors INTEGER NOT NULL DEFAULT 0
);

CREATE UNIQUE INDEX report_datetime_ind ON visitor_reports(report_datetime);
