-- --------------------------------------------------------
-- This file is part of TwistPHP.
--
-- TwistPHP is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- TwistPHP is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with TwistPHP.  If not, see <http://www.gnu.org/licenses/>.
--
-- @author     Shadow Technologies Ltd. <contact@shadow-technologies.co.uk>
-- @license    https://www.gnu.org/licenses/gpl.html LGPL License
-- @link       http://twistphp.com/
--
-- --------------------------------------------------------
--
-- All SQL queries that are required to setup this package
-- as a fresh installation
--
-- New tables       add a CREATE query
-- New records      add an INSERT query
--
-- To Add the Twist table prefix you must use the following syntax /*TABLE_PREFIX*/`table_name`
--
-- ------------------------------------------------------

CREATE TABLE IF NOT EXISTS /*TWIST_DATABASE_TABLE_PREFIX*/`ws_chat_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `receipt_id` int(11) DEFAULT NULL,
  `message` text COLLATE utf8_unicode_ci NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS /*TWIST_DATABASE_TABLE_PREFIX*/`ws_chat_rooms` (
  `id` int(11) NOT NULL,
  `name` char(128) COLLATE utf8_unicode_ci NOT NULL,
  `key` char(64) COLLATE utf8_unicode_ci NOT NULL,
  `users` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'a comma separated list of all user in the room',
  `private` enum('1','0') COLLATE utf8_unicode_ci NOT NULL COMMENT 'Set to ''1'' for a room status to be private',
  `private_group` enum('1','0') COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `fixed` enum('1','0') COLLATE utf8_unicode_ci NOT NULL COMMENT 'Set to ''1'' to make the room permanent (stays even with no users present)',
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;