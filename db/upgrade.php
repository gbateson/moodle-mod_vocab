<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * db/upgrade.php: Upgrade code for mod_vocab
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

/**
 * xmldb_vocab_upgrade
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $oldversion
 * @return xxx
 *
 * TODO: Finish documenting this function
 */
function xmldb_vocab_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    $newversion = 2023072402;
    if ($oldversion < $newversion) {
        update_capabilities('mod/vocab');
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    $newversion = 2023100305;
    if ($oldversion < $newversion) {
        xmldb_vocab_check_structure($dbman);
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    $newversion = 2023101907;
    if ($oldversion < $newversion) {
        xmldb_vocab_check_structure($dbman, ['vocab_wordlists']);
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    $newversion = 2023102509;
    if ($oldversion < $newversion) {
        // Add attempt score|count|type|delay fields.
        xmldb_vocab_check_structure($dbman, ['vocab']);
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    $newversion = 2023102510;
    if ($oldversion < $newversion) {

        // Rename "vocab_wordlists" table to "vocab_word_instances".
        $tablenames = ['vocab_wordlists' => 'vocab_word_instances'];
        xmldb_vocab_rename_tables($dbman, $tablenames);

        // Add tables for games and word attempts.
        xmldb_vocab_check_structure($dbman);
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    $newversion = 2023103012;
    if ($oldversion < $newversion) {

        // Define old/new names for vocabtool plugins.
        $names = [
            'vocabtool_addphpdocs' => 'vocabtool_phpdocs',
            'vocabtool_editdata' => 'vocabtool_dictionary',
            'vocabtool_editlist' => 'vocabtool_wordlist',
            'vocabtool_importdata' => 'vocabtool_import',
        ];

        // Rename vocabtool plugins.
        $table = 'config_plugins';
        $select = $DB->sql_like('plugin', '?');
        $params = ['vocabtool_%'];
        if ($records = $DB->get_records_select($table, $select, $params)) {
            foreach ($records as $record) {
                $record->plugin = strtr($record->plugin, $names);
                $DB->update_record($table, $record);
            }
        }

        // Remove vocabtool capabilities from all roles.
        $table = 'role_capabilities';
        $select = $DB->sql_like('capability', '?');
        $params = ['vocabtool%'];
        if ($roles = $DB->get_records_select($table, $select, $params)) {
            foreach ($roles as $role) {
                unassign_capability($role->capability, $role->id);
            }
            $DB->delete_records_list($table, 'id', array_keys($roles));
        }

        // Remove all vocabtool capabilities.
        $table = 'capabilities';
        $select = $DB->sql_like('name', '?');
        $params = ['vocabtool%'];
        if ($caps = $DB->get_records_select($table, $select, $params)) {
            $DB->delete_records_list($table, 'id', array_keys($caps));
        }
    }

    $newversion = 2023103113;
    if ($oldversion < $newversion) {
        // Add "expandmycourses" and "pagelayout" fields to "vocab" table.
        xmldb_vocab_check_structure($dbman, ['vocab']);
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    $newversion = 2023110114;
    if ($oldversion < $newversion) {
        // Rename field "expandmycourses" to "expandnavigation".
        $field = new xmldb_field('expandmycourses', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        xmldb_vocab_rename_fields($dbman, 'vocab', ['expandnavigation' => $field]);
    }

    /*/////////////////////////////////////
    // Interim updates are all obviated by
    // full structure check for 2024021261
    /////////////////////////////////////*/

    $newversion = 2024021261;
    if ($oldversion < $newversion) {
        xmldb_vocab_check_structure($dbman);
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    $newversion = 2024022271;
    if ($oldversion < $newversion) {
        // Remove prompt settings referring to 'promptai' as this is no longer used. 
        $DB->delete_records('vocab_config_settings', ['name' => 'promptai']);
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    return true;
}

/**
 * Rename fields without failing if the field to delete is
 * not found or a field with the new name already exists.
 *
 * @param object $dbman the Moodle database manager
 * @param string $table name of a table in the database
 * @param array $fields of database field names
 * @return void, (but may drop or rename fields in the database)
 */
function xmldb_vocab_rename_fields($dbman, $table, $fields) {
    $table = new xmldb_table($table);
    foreach ($fields as $newname => $oldfield) {
        if ($dbman->field_exists($table, $oldfield)) {
            if ($dbman->field_exists($table, $newname)) {
                $dbman->drop_field($table, $oldfield);
            } else {
                $dbman->rename_field($table, $oldfield, $newname);
            }
        }
    }
}

/**
 * Rename tables without failing if the table to delete is
 * not found or a table with the new name already exists.
 *
 * @param object $dbman the database manager
 * @param array $tablenames map old names to new names
 * @return void (but may drop or rename tables in the database)
 */
function xmldb_vocab_rename_tables($dbman, $tablenames) {
    foreach ($tablenames as $oldname => $newname) {
        if ($dbman->table_exists($oldname)) {
            $table = new xmldb_table($oldname);
            if ($dbman->table_exists($newname)) {
                $dbman->drop_table($table);
            } else {
                $dbman->rename_table($table, $newname);
            }
        }
    }
}

/**
 * xmldb_vocab_check_structure
 *
 * @uses $CFG
 * @uses $DB
 * @param object $dbman the database manager
 * @param array $tablenames (optional, default=null) specific tables to check
 * @param array $tableprefix (optional, default=vocab) the prefix for DB tables belonging to this plugin
 * @param array $pluginname (optional, default=mod_vocab) the full frakenstyle name of this plugin e.g. mod_vocab
 * @param array $plugindir (optional, default=mod/vocab) the relative path to main folder for this plugin's directory
 * @return void (but may update database structure)
 */
function xmldb_vocab_check_structure($dbman, $tablenames=null, $tableprefix='vocab',
                                    $pluginname='mod_vocab', $plugindir='mod/vocab') {
    global $CFG, $DB;

    // To see what tables/fields/indexes were added/changed/dropped,
    // set the $debug flag to TRUE during development of this script.
    $debug = false;

    // Define array [$pluginname => boolean] to cache
    // whether or not we have checked all tables for this plugin.
    static $checkedall = [];

    // Define array [$pluginname => [$tablenames]] to cache
    // which tables for this plugin have already been checked.
    static $checked = [];

    // If this is the frst time to check any tables for this plugin,
    // initialize its $checkedall flag and $checked array.
    if (! array_key_exists($pluginname, $checkedall)) {
        $checkedall[$pluginname] = false;
        $checked[$pluginname] = [];
    }

    // If we have already checked all tables for this plugin,
    // we can stop here.
    if ($checkedall[$pluginname]) {
        return true;
    }

    // If we are going to check all tables for this $plugin,
    // then we can set its $checkall flag to "true".
    if ($tablenames === null) {
        $checkedall[$pluginname] = true;
    }

    // Locate the XML file for this plugin, and try to read it.
    $filepath = "/$plugindir/db/install.xml";
    $file = new xmldb_file($CFG->dirroot.$filepath);

    if (! $file->fileExists()) {
        // Presumably this would only happen on a development site.
        $error = "XML file not found: $filepath";
        throw new ddl_exception('ddlxmlfileerror', null, $error);
    }

    // Parse the the structure of the XML.
    $loaded = $file->loadXMLStructure();
    $structure = $file->getStructure();

    // Check that the XML file could be loaded.
    if (! $file->isLoaded()) {
        if ($structure && ($error = $structure->getAllErrors())) {
            $error = implode (', ', $error);
            $error = "Errors found in XMLDB file ($filepath): ". $error;
        } else {
            $error = "XMLDB file not loaded ($filepath)";
        }
        throw new ddl_exception('ddlxmlfileerror', null, $error);
    }

    // Get a list of tables for this plugin that are defined in the XML.
    if (! $tables = $structure->getTables()) {
        $error = "No tables found in XML file ($filepath)";
        throw new ddl_exception('ddlxmlfileerror', null, $error);
    }

    // Get a list of "$errors" in the schema. Actually, an "$error"
    // is really just something different between the XML schema
    // and the current structure of the Moodle database.
    $errors = $dbman->check_database_schema($structure);
    if ($tablenames) {
        $keys = array_values($tablenames);
    } else {
        $keys = array_keys($errors);
    }

    // Extract only errors relating to tables for this plugin.
    $keys = preg_grep('/^'.$tableprefix.'(_|$)/', $keys);
    $errors = array_intersect_key($errors, array_flip($keys));

    // Loop through $tablenames mentioned in the $errors for this plugin.
    foreach ($errors as $tablename => $messages) {

        // Skip tables that have already been checked.
        if (array_key_exists($tablename, $checked[$pluginname])) {
            continue;
        }
        $checked[$pluginname][$tablename] = true;

        $i = $file->findObjectInArray($tablename, $tables);
        if (is_numeric($i)) {
            // A table in the XML file.
            // It may or may  not exist in the DB.
            $table = $tables[$i];
        } else {
            // A table that is in the DB but not in the XML file.
            // In other words, a table that is to be removed.
            // Perhaps it should have been renamed, but it's too late now.
            $table = new xmldb_table($tablename);
        }

        // Get current (uncached) info about columns and indexes in database.
        $columns = $DB->get_columns($tablename, false);
        $indexes = $DB->get_indexes($tablename, false);

        // If we try to change any fields that are indexed, the $dbman will abort with an error.
        // As a workaround, we make a note of which fields are used in the keys/indexes,
        // and then, if any of them are to be changed, we first remove the key/index,
        // then change the field and finally add the key/index back to the table.

        $special = (object)[
            'keyfields' => [],
            'indexfields' => [],
        ];

        $dropped = (object)[
            'keys' => [],
            'indexes' => [],
        ];

        // Map each key field onto an array of keys that use the field.
        foreach ($table->getKeys() as $key) {
            foreach ($key->getFields() as $field) {
                if ($key->getType() == XMLDB_KEY_PRIMARY) {
                    // We can never alter the "id" field.
                    continue;
                }
                if (empty($special->keyfields[$field])) {
                    $special->keyfields[$field] = [];
                }
                $special->keyfields[$field][] = $key;
            }
        }

        // Map each index field onto an array of indexes that use the field.
        foreach ($table->getIndexes() as $index) {
            foreach ($index->getFields() as $field) {
                if (empty($special->indexfields[$field])) {
                    $special->indexfields[$field] = [];
                }
                $special->indexfields[$field][] = $index;
            }
        }

        // Loop through the error messages relating to this table.
        foreach ($messages as $message) {

            switch (true) {

                // Moodle <= 2.7 uses "Table".
                // Moodle >= 2.8 uses "table".
                case preg_match('/[Tt]able is missing/', $message):
                    $dbman->create_table($table);
                    if ($debug) {
                        echo "Table $tablename was created<br>";
                    }
                    break;

                // Moodle <= 2.7 uses "Table".
                // Moodle >= 2.8 uses "table".
                case preg_match('/[Tt]able is not expected/', $message):
                    $dbman->drop_table($table);
                    if ($debug) {
                        echo "Table $tablename was dropped<br>";
                    }
                    break;

                // Moodle <= 2.7 uses "Field".
                // Moodle >= 2.8 uses "column".
                case preg_match('/(Field|column) (.*?) (.*)/', $message, $match):
                    $name = trim($match[2], "'");
                    $text = trim($match[3]);

                    $fields = $table->getFields();
                    $i = $table->findObjectInArray($name, $fields);

                    if (is_numeric($i)) {
                        $field = $fields[$i];
                    } else {
                        $field = new xmldb_field($name);
                    }

                    if (array_key_exists($name, $special->keyfields)) {
                        foreach ($special->keyfields[$name] as $key) {
                            // There is no "key_exists" method, but "index_exists"
                            // seems to work if we give it an "xmldb_index" object.
                            $index = new xmldb_index($key->getName(), $key->getType(), $key->getFields());
                            if ($dbman->index_exists($table, $index)) {
                                $dbman->drop_key($table, $key);
                                $dropped->keys[] = $key;
                            }
                        }
                        // Remove this field from the list of keyfields,
                        // as it will not be needed again.
                        unset($special->keyfields[$name]);
                    }

                    if (array_key_exists($name, $special->indexfields)) {
                        foreach ($special->indexfields[$name] as $index) {
                            if ($dbman->index_exists($table, $index)) {
                                $dbman->drop_index($table, $index);
                                $dropped->indexes[] = $index;
                            }
                        }
                        // Remove this field from the list of indexfields,
                        // as it will not be needed again.
                        unset($special->indexfields[$name]);
                    }

                    if (substr($text, 0, 15) == 'is not expected') {
                        // E.g. column 'xyz' is not expected.
                        if ($dbman->field_exists($table, $field)) {
                            $dbman->drop_field($table, $field);
                            if ($debug) {
                                echo "Field $name was dropped from table $tablename<br>";
                            }
                        }
                    } else {
                        // E.g. column 'xyz' is missing.
                        if ($dbman->field_exists($table, $field)) {
                            $dbman->change_field_type($table, $field);
                            if ($debug) {
                                echo "Field $name was updated<br>";
                            }
                        } else {
                            $dbman->add_field($table, $field);
                            if ($debug) {
                                echo "Field $name was added<br>";
                            }
                        }
                    }
                    break;

                // Note: early versions of Moodle may not have this.
                case preg_match('/CREATE(.*?)INDEX(.*?)ON(.*?);/', $message, $match):
                    $DB->execute(rtrim($match[0], '; '));
                    if ($debug) {
                        echo 'Index '.$match[1].' was added<br>';
                    }
                    break;

                // Note: early versions of Moodle may not have this.
                case preg_match("/Unexpected index '(\w+)'/", $message, $match):
                    $name = $match[1];
                    if (array_key_exists($name, $indexes)) {
                        $index = new xmldb_index($name);
                        $index->setFromADOIndex($indexes[$name]);
                        if ($dbman->index_exists($table, $index)) {
                            $dbman->drop_index($table, $index);
                        }
                        unset($indexes[$name]);
                        if ($debug) {
                            echo 'Index '.$match[1].' was dropped<br>';
                        }
                    }
                    break;

                default:
                    if ($debug) {
                        echo '<p>Unknown XMLDB error in '.$pluginname.':<br>'.$message.'</p>';
                        die;
                    }
            }
        }

        // Restore any keys that were dropped.
        foreach ($dropped->keys as $key) {
            $index = new xmldb_index($key->getName(), $key->getType(), $key->getFields());
            if (! $dbman->index_exists($table, $index)) {
                $dbman->add_key($table, $key);
            }
        }

        // Restore any indexes that were dropped.
        foreach ($dropped->indexes as $index) {
            if (! $dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
    }
}
