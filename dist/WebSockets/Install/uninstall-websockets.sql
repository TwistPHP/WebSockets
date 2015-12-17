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
-- To Add the Twist table prefix you must use the following syntax /*TWIST_DATABASE_TABLE_PREFIX*/`table_name`
--
-- ------------------------------------------------------

-- --------------------------------------------------------

SELECT * FROM /*TWIST_DATABASE_TABLE_PREFIX*/`ws_uninstall`;

DROP TABLE IF EXISTS /*TWIST_DATABASE_TABLE_PREFIX*/`ws_chat_history`;

DROP TABLE IF EXISTS /*TWIST_DATABASE_TABLE_PREFIX*/`ws_chat_rooms`;