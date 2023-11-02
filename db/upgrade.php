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

defined('MOODLE_INTERNAL') || die();

/**
 * xmldb_vocab_upgrade
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $oldversion
 * @return xxx
 * @todo Finish documenting this function
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
        xmldb_vocab_check_structure($dbman, array('vocab_wordlists'));
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    $newversion = 2023102509;
    if ($oldversion < $newversion) {
        // Add attempt score|count|type|delay fields.
        xmldb_vocab_check_structure($dbman, array('vocab'));
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    $newversion = 2023102510;
    if ($oldversion < $newversion) {

        // Rename "vocab_wordlists" table to "vocab_word_instances".
        $tablenames = array('vocab_wordlists' => 'vocab_word_instances');
        xmldb_vocab_rename_tables($dbman, $tablenames);

        // Add tables for games and word attempts.
        xmldb_vocab_check_structure($dbman);
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    $newversion = 2023103012;
    if ($oldversion < $newversion) {

        // Define old/new names for vocabtool plugins.
        $names = array(
            'vocabtool_addphpdocs' => 'vocabtool_phpdocs',
            'vocabtool_editdata' => 'vocabtool_dictionary',
            'vocabtool_editlist' => 'vocabtool_wordlist',
            'vocabtool_importdata' => 'vocabtool_import'
        );

        // Rename vocabtool plugins.
        $table = 'config_plugins';
        $select = $DB->sql_like('plugin', '?');
        $params = array('vocabtool_%');
        if ($records = $DB->get_records_select($table, $select, $params)) {
            foreach ($records as $record) {
                $record->plugin = strtr($record->plugin, $names);
                $DB->update_record($table, $record);
            }
        }

        // Remove vocabtool capabilities from all roles.
        $table = 'role_capabilities';
        $select = $DB->sql_like('capability', '?');
        $params = array('vocabtool%');
        if ($roles = $DB->get_records_select($table, $select, $params)) {
            foreach ($roles as $role) {
                unassign_capability($role->capability, $role->id);
            }
            $DB->delete_records_list($table, 'id', array_keys($roles));
        }

        // Remove all vocabtool capabilities.
        $table = 'capabilities';
        $select = $DB->sql_like('name', '?');
        $params = array('vocabtool%');
        if ($caps = $DB->get_records_select($table, $select, $params)) {
            $DB->delete_records_list($table, 'id', array_keys($caps));
        }
    }

    $newversion = 2023103113;
    if ($oldversion < $newversion) {
        // Add "expandmycourses" and "pagelayout" fields to "vocab" table.
        xmldb_vocab_check_structure($dbman, array('vocab'));
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    $newversion = 2023110114;
    if ($oldversion < $newversion) {
        // Rename field "expandmycourses" to "expandnavigation". 
        $field = new xmldb_field('expandmycourses', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        xmldb_vocab_rename_fields($dbman, 'vocab', array('expandnavigation' => $field));
    }

    $newversion = 2023110215;
    if ($oldversion < $newversion) {
        // Add new table, "vocab_word_states".
        xmldb_vocab_check_structure($dbman, array('vocab_word_states'));
        upgrade_mod_savepoint(true, "$newversion", 'vocab');
    }

    return true;
}

/**
 * Rename fields without failing if the field to delete is
 * not found or a field with the new name already exists.
 *
 * @param object $dbman
 * @param array $fieldnames
 * @return void (but may rename or drop field from database)
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
 * @param object $dbman
 * @param array $tablenames
 * @return void (but may rename or drop table from database)
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
 * @param xxx $dbman
 * @param xxx $tablenames (optional, default=null)
 * @return void (but may update database structure)
 * @todo Finish documenting this function
 */
function xmldb_vocab_check_structure($dbman, $tablenames=null) {
    global $CFG, $DB;

    static $checkedall = false;
    static $checked = array();

    if ($checkedall) {
        return true;
    }

    if ($tablenames === null) {
        $checkedall = true;
    }

    $filepath = '/mod/vocab/db/install.xml';
    $xmldb_file = new xmldb_file($CFG->dirroot.$filepath);

    $loaded = $xmldb_file->loadXMLStructure();
    $structure = $xmldb_file->getStructure();

    if (! $xmldb_file->fileExists()) {
        $error = "XML file not found: $filepath";
        throw new ddl_exception('ddlxmlfileerror', null, $error);
    }

    if (! $xmldb_file->isLoaded()) {
        if ($structure && ($error = $structure->getAllErrors())) {
            $error = implode (', ', $error);
            $error = "Errors found in XMLDB file ($filepath): ". $error;
        } else {
            $error = "XMLDB file not loaded ($filepath)";
        }
        throw new ddl_exception('ddlxmlfileerror', null, $error);
    }

    if (! $xmldb_tables = $structure->getTables()) {
        $error = "No tables found in XML file ($filepath)";
        throw new ddl_exception('ddlxmlfileerror', null, $error);
    }

    $errors = $dbman->check_database_schema($structure);
    if ($tablenames) {
        $keys = array_values($tablenames);
    } else {
        $keys = array_keys($errors);
    }
    $keys = preg_grep('/^vocab(_|$)/', $keys);
    $errors = array_intersect_key($errors, array_flip($keys));

    foreach ($errors as $tablename => $messages) {

        if (array_key_exists($tablename, $checked)) {
            continue;
        }
        $checked[$tablename] = true;

        $i = $xmldb_file->findObjectInArray($tablename, $xmldb_tables);
        if (is_numeric($i)) {
            // A table in the XML file.
            // It may or may  not exist in the DB.
            $table = $xmldb_tables[$i];
        } else {
            // A table that is in the DB but not in the XML file.
            // In other words, a table that is to be removed.
            // Perhaps it should have been renamed, but it's too late now.
            $table = new xmldb_table($tablename);
        }

        // Get current (uncached) info about columns and indexes in database.
        $columns = $DB->get_columns($tablename, false);
        $indexes = $DB->get_indexes($tablename, false);

        // We don't want to change any fields that are indexed,
        // because the $dbman will abort with an error.
        // As a workaround, we skip all fields that are indexed.
        //
        // To do this properly we probably need to remove the index,
        // change the field and then add the index again.
        // It is possible because phpMyAdmin can do it with ease.

        $special = (object)array(
            'keyfields' => array(),
            'indexfields' => array()
        );

        $dropped = (object)array(
            'keys' => array(),
            'indexes' => array()
        );

        foreach ($table->getKeys() as $key) {
            foreach ($key->getFields() as $field) {
                if ($key->getType() == XMLDB_KEY_PRIMARY) {
                    continue;
                }
                if (empty($special->keyfields[$field])) {
                    $special->keyfields[$field] = array();
                }
                $special->keyfields[$field][] = $key;
            }
        }

        foreach ($table->getIndexes() as $index) {
            foreach ($index->getFields() as $field) {
                if (empty($special->indexfields[$field])) {
                    $special->indexfields[$field] = array();
                }
                $special->indexfields[$field][] = $index;
            }
        }

        foreach ($messages as $message) {

            switch (true) {

                // Moodle <= 2.7 uses "Table"
                // Moodle >= 2.8 uses "table"
                case preg_match('/[Tt]able is missing/', $message):
                    $dbman->create_table($table);
                    //echo "Table $tablename was created<br>";
                    break;


                // Moodle <= 2.7 uses "Table"
                // Moodle >= 2.8 uses "table"
                case preg_match('/[Tt]able is not expected/', $message):
                    $dbman->drop_table($table);
                    //echo "Table $tablename was dropped<br>";
                    break;

                // Moodle <= 2.7 uses "Field"
                // Moodle >= 2.8 uses "column"
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
                            // There is no "key_exists" method, but "index_exists" seems to work if we give it an "xmldb_index" object.
                            if ($dbman->index_exists($table, new xmldb_index($key->getName(), $key->getType(), $key->getFields()))) {
                                $dbman->drop_key($table, $key);
                                $dropped->keys[] = $key;
                            }
                        }
                        unset($special->keyfields[$name]); // We don't need this information any more.
                    }

                    if (array_key_exists($name, $special->indexfields)) {
                        foreach ($special->indexfields[$name] as $index) {
                            if ($dbman->index_exists($table, $index)) {
                                $dbman->drop_index($table, $index);
                                $dropped->indexes[] = $index;
                            }
                        }
                        unset($special->indexfields[$name]); // We don't need this information any more.
                    }

                    if (substr($text, 0, 15) == 'is not expected') {
                        // e.g. column 'xyz' is not expected
                        if ($dbman->field_exists($table, $field)) {
                            $dbman->drop_field($table, $field);
                            //echo "Field $name was dropped from table $tablename<br>";
                        }
                    } else {
                        // e.g. column 'xyz' is missing
                        if ($dbman->field_exists($table, $field)) {
                            $dbman->change_field_type($table, $field);
                            //echo "Field $name was updated<br>";
                        } else {
                            $dbman->add_field($table, $field);
                            //echo "Field $name was added<br>";
                        }
                    }
                    break;

                // Note: early versions of Moodle may not have this.
                case preg_match('/CREATE(.*?)INDEX(.*?)ON(.*?);/', $message, $match):
                    $DB->execute(rtrim($match[0], '; '));
                    //echo 'Index '.$match[1].' was added<br>';
                    break;

                // Note: early versions of Moodle may not have this.
                case preg_match("/Unexpected index '(\w+)'/", $message, $match):
                    $name = $match[1];
                    if (array_key_exists($name, $indexes)) {
                        $index = new xmldb_index($name);
                        $index->setFromADOIndex($indexes[$name]);
                        $dbman->drop_index($table, $index);
                        unset($indexes[$name]);
                        //echo 'Index '.$match[1].' was dropped<br>';
                    }
                    break;

                default:
                    echo '<p>Unknown XMLDB error in mod_vocab:<br>'.$message.'</p>';
                    //die;
            }
        }

        foreach ($dropped->keys as $key) {
            if (! $dbman->index_exists($table, new xmldb_index($key->getName(), $key->getType(), $key->getFields()))) {
                $dbman->add_key($table, $key);
            }
        }

        foreach ($dropped->indexes as $index) {
            if (! $dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
    }
}