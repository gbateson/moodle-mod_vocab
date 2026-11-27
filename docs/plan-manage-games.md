# Plan: managing games on the activity page

Status: 2026-11-27. Steps 1 to 8 done.

## Goal

Teachers add, edit, reorder and remove the games in a Vocabulary activity
from the activity page (`view.php`), not from the settings form. This works
the same way mod_englishcentral manages its videos.

- Each game in the activity is shown as a tile, like the tiles students see now.
- A **＋** tile adds a game.
- A **🗑** target removes a game. Teachers drag a tile onto it.
- Clicking a tile opens that game's settings: in a popup when JavaScript is
  available, otherwise on a separate page.

The activity always exists before any game is added, so every change can be
saved straight away.

## Decisions made

| Topic | Decision |
|---|---|
| Where games are managed | On `view.php`, for users with `mod/vocab:manage` (this capability already exists). |
| Settings form | Keeps **Stages of learning**. Loses the **Games** section. |
| When changes save | Immediately, one game at a time. |
| Popup and fallback page | One `\core_form\dynamic_form` class. It is shown in `core_form/modalform` with JavaScript, and on `game/edit.php` without it. |
| Stage per game | One game instance = one stage. To use the same game at two stages, add it twice. |
| Reordering | `core/sortable_list` (accessible, no jQuery UI). |
| Deleting | Drag to 🗑, or *Delete* in the tile's ⋮ menu for keyboard users. Always confirm. A game that has attempts is hidden (`enabled = 0`), not deleted, so students' scores are kept. |
| Hiding and restoring | The ⋮ menu also has *Hide* / *Show*. Hidden games can be restored at any time. |
| Greyed-out tiles | Teachers see hidden games, and games at stages that are not enabled, as greyed-out tiles. Students don't see them at all. |
| New activities | Start with no games, in the same way as a new Moodle quiz starts with no questions. |
| Backup of words | Only the words themselves (language, lemma and word), not their dictionary details, which belong to the site. On another site, words are restored without details; the import tool can add them. |
| Game titles | Each game instance has its own title. The suggested title is the game's name and stage, e.g. "Quiz (Recognize)", and teachers can override it. An empty title means "use the suggested one", so the suggestion follows if the stage changes. This needs a new `title` field in `vocab_game_instances`. |

## Open questions

None at present.

## Before the change

- `vocab_game_instances` already supports many games per activity: `sortorder`,
  `stage`, `configdata`, `enabled`. The only schema change is the new `title` field.
- [mod_form.php](mod_form.php): `add_games()` adds one checkbox for each
  game type and stage, plus each game's `add_settings()`.
  `stages_and_games_preprocessing()` loads them.
- [lib.php](lib.php): `vocab_save_stages_and_games()` collects the game fields
  and calls `progress::save_game_instances()`.
- [classes/progress.php](classes/progress.php): `save_game_instances()` keys
  instances by *game type + stage*, so only one game of each type per stage
  is possible. This was the main limit to remove. It also hid any game that
  was not ticked in the form, so it had to go at the same time as the new
  page was added.
- [classes/gamebase.php](classes/gamebase.php): the static `add_settings()`
  and `get_settings()` take a field-name prefix. With prefix `''` they can be
  reused unchanged in the new form.
- [renderer.php](renderer.php): `view_games()`, `get_games()` and
  `game_button()` draw the tiles. `get_games()` still has placeholder
  "Vocabulary game N" tiles. These should go.
- **Backup is broken already**, and switched off: `vocab_supports(FEATURE_BACKUP_MOODLE2)`
  returns false. `backup/moodle2/backup_vocab_stepslib.php`
  refers to `vocab_games` by `vocabid`, `vocab_game_scores`, `taskid` and
  `vocab_questions`. These are leftovers from TaskChain and don't match
  `install.xml`. Once teachers build their own sets of games, backup and
  restore must work.

## Steps

Each step should leave the plugin working, and be committed to SVN and
GitHub on its own.

### 1. Game instance API (`classes/progress.php`, `classes/gamebase.php`, `db/`) — done

- Add a `title` field (char 255, default `''`) to `vocab_game_instances`, with
  an upgrade step.
- `gamebase::get_default_title(string $stage)`: the suggested title, e.g. "Quiz (Recognize)".
- `gamebase::get_label()`: the instance's title, or the suggested title if it is empty.
- `progress::add_game_instance($vocabid, $name, $stage, $config, $title = '')`:
  appends at the end of the sort order, and returns the new id.
- `progress::update_game_instance($id, $stage, $config, $title)`.
- `progress::count_game_instance_attempts($id)`: used for the delete confirmation.
- `progress::delete_game_instance($id)`: hides the game if it has attempts;
  otherwise deletes it. Returns whether it was deleted.
- `progress::set_game_instance_enabled($id, $enabled)`: hide or restore.
- `progress::reorder_game_instances($vocabid, $ids)`: the given ids first, in
  that order, then any others in their current order. It reuses the existing
  trick of moving sort orders out of the way first, because of the unique
  index on `(vocabid, sortorder)`.
- Keep `save_game_instances()` until step 6, then remove it.
- PHPUnit tests for all of these.

### 2. Game settings form (`classes/gameform.php`) — done

- `\mod_vocab\gameform extends \core_form\dynamic_form`.
- `get_context_for_dynamic_submission()`: the module context, from `cmid`.
- `check_access_for_dynamic_submission()`: `require_capability('mod/vocab:manage')`.
- `definition()`:
  - hidden fields `cmid`, `id` (instance, 0 for new) and `game` (type name);
  - a **Stage** select, limited to stages that are enabled in this activity and
    supported by the game;
  - a **Title** field, empty by default, with the suggested title shown as
    its placeholder;
  - then `$class::add_settings($mform, '', $context)`.
- `validation()`: calls a new optional `gamebase::validate_settings()` hook.
- `set_data_for_dynamic_submission()`: loads the instance's `stage` and `configdata`.
- `process_dynamic_submission()`: calls `add_game_instance()` or
  `update_game_instance()`, and returns the instance id.
- `get_page_url_for_dynamic_submission()`: `game/edit.php`.

### 3. Pages that work without JavaScript — done

- `game/edit.php?id=<instanceid>`: edit a game.
- `game/edit.php?cmid=<cmid>&game=<type>`: add a game.
- `game/add.php?cmid=<cmid>`: choose a game type, then go to `edit.php`.
  Skipped when only one type is installed.
- `game/delete.php?id=<instanceid>`: confirmation page, then delete or hide.
- `game/hide.php?id=<instanceid>&enabled=0|1&sesskey=…`: hide or show, then
  return to `view.php`.
- Each page runs the same `gameform` as a normal page form. Cancel and Save
  both return to `view.php`.

### 4. Web service functions (`db/services.php`, `classes/external/`) — done

- `mod_vocab_delete_game_instance(id)`
- `mod_vocab_reorder_game_instances(vocabid, ids[])`
- `mod_vocab_set_game_instance_enabled(id, enabled)`: hide or show.
- All three are AJAX-enabled and check `mod/vocab:manage`.
- Adding and editing don't need web service functions: `dynamic_form`
  handles its own AJAX.
- Bump `version.php`.

### 5. Tiles on `view.php` (`renderer.php`, `templates/`, `amd/src/`) — done

- Teachers' tiles are drawn by `templates/game_manager.mustache`, with
  `data-instanceid` on each tile. Students' tiles are drawn as before.
- For teachers (`mod/vocab:manage`):
  - a **＋** tile, which links to `game/add.php`;
  - a **🗑** drop target, shown only when JavaScript is available;
  - a ⋮ action menu on each tile: *Edit*, *Preview*, *Hide* / *Show*, *Delete*
    (*Duplicate* is moved to "Possibly later");
  - hidden games, and games at stages that are not enabled, greyed out;
  - tiles link to `game/edit.php`.
- Students see the tiles as now. The teacher controls are not output for them.
- The "Vocabulary game N" tiles in `get_games()` are only for the demo mode
  (`activity::is_demo()`), so they stay.
- `amd/src/managegames.js`:
  - intercepts tile and ＋ clicks and opens the `gameform` in `ModalForm`;
  - for ＋ with several game types, shows a small chooser first;
  - reloads the page after a save (simplest first version; re-rendering the
    tiles in place can come later);
  - uses `core/sortable_list` for reordering, then calls the reorder web
    service function;
  - handles drops on 🗑 and *Delete* in the menu: it confirms with
    `core/notification`, mentioning the attempt count when there are attempts,
    then calls the delete web service function and reloads the page.
- Styles in `styles.css`, using the existing tile colours.

### 6. Settings form clean-up (`mod_form.php`, `lib.php`) — done

- Remove `add_games()`, the game parts of `stages_and_games_preprocessing()`,
  and the game parts of `vocab_save_stages_and_games()`.
- In the **Games** section's place, add a short note: "Games are added and
  arranged on the activity page". Link to it when the activity exists.
- Remove `progress::save_game_instances()`.
- `gamebase::add_settings()` keeps its `$prefix` parameter; the form passes `''`.
- Students no longer see games at stages that are not enabled, and
  `game/quiz/play.php` refuses hidden games and games at those stages,
  except for teachers.

### 7. Backup and restore — done

Backup is switched off (`FEATURE_BACKUP_MOODLE2` is false), and
`backup/moodle2/` holds TaskChain code that refers to tables that do not exist.
So this step rewrites backup and restore, for the whole activity, then
switches backup on. It must work in Moodle 4.x and 5.x.

#### What is backed up

| Data | Tables | Notes |
|---|---|---|
| The activity | `vocab` | All fields, and the `intro` file area. |
| Stages | `vocab_stages` | |
| Word list | `vocab_word_instances` | Each word as **language code, lemma and word**, because words are shared by the whole site (`vocab_words`, `vocab_lemmas`, `vocab_langs`). |
| Games | `vocab_game_instances` | The game's **name** (e.g. "quiz") instead of `gameid`, and `configdata`. |
| Questions about the words | `vocab_word_questions` | The links of the activity's words. The questions are marked for backup: each version (4.x includes the course's whole question bank) and its bank entry (5.x includes only those entries). |
| *With user data:* progress | `vocab_word_stages`, `vocab_word_states` | |
| *With user data:* attempts | `vocab_game_attempts`, `vocab_word_usages`, `vocab_word_attempts` | Quiz attempts include their question usages (`add_question_usages()`), as mod_quiz does. |

Grades are restored by Moodle's gradebook, as for other activities.

#### Restore

- **Words**: find the language, lemma and word, or create them, as the word
  list tool does. On the same site, the existing words are found, so their
  dictionary details and question links still apply.
- **Games**: find `gameid` from the game's name. A game that is not
  installed is skipped.
- **Ids inside JSON** are mapped:
  - `configdata.categoryid`, in `after_restore()`, because Moodle 5.x moves
    restored question categories into question banks at the end of a restore.
    The quiz uses the restored copy, if it is in one of the target course's
    question banks; otherwise, on the same site, the original category, if it
    still exists (as Moodle 5.x does for quizzes that use a shared question
    bank); otherwise *Any category*;
  - the quiz attempt's `data.slots` (slot => word instance id) through
    `vocab_word_instance`.
- **Question links**: in `after_restore()`, the words are linked to the
  restored copies of the questions that are in the target course's question
  banks. On the same site, the words are the same words, so their links to the
  original questions still apply.
- **Attempts**: `uniqueid` is the restored question usage
  (`inform_new_usage_id()`). An attempt is inserted before its usage is
  restored, because only quiz attempts have one.

#### What happens to the questions

| Restore | Moodle 4.x | Moodle 5.x |
|---|---|---|
| Activity backup, into another course on the same site | The course's question bank is copied into the new course, and the quiz uses the copy. | The quiz keeps using the original shared question bank, as mod_quiz does. Questions used in restored attempts are saved in a new bank ("Questions saved from context …"). |
| Whole course backup, into a new course | The quiz uses the copy in the new course. | The question bank activities are restored too, and the quiz uses the copy in the new course. |
| Duplicate in the same course | The copy uses the same category. | The same. |

#### Not backed up

- The rest of the dictionary: definitions, multimedia, frequencies,
  pronunciations, relationships, attributes and samples. They belong to the
  site, not to an activity (see *Decisions made*).
- The question bank tool's log of AI tasks, and AI settings.

#### Done

- `backup_vocab_stepslib.php` extends `backup_questions_activity_structure_step`,
  and `backup_vocab_activity_task` calculates the question categories, as
  mod_quiz does, and encodes links to `view.php` and `index.php`.
- `restore_vocab_stepslib.php` extends
  `restore_questions_activity_structure_step`, and `restore_vocab_activity_task`
  decodes the links and restores legacy log rules.
- `vocab_supports()`: `FEATURE_BACKUP_MOODLE2` and `FEATURE_USES_QUESTIONS`
  are true. Moodle already treated mod_vocab as using questions, because of
  `mod_vocab_question_pluginfile()`, and warned that the feature was not
  declared.
- PHPUnit (`tests/backup/backup_restore_test.php`), on 4.5 and 5.3: an
  activity backup restored into another course with and without user data, a
  whole course backup restored into a new course, and a duplicate in the same
  course.

### 8. Tests, strings and finish — done

- Behat:
  - add, edit and delete a game without JavaScript;
  - the same with JavaScript (`@javascript`);
  - a student does not see the teacher controls.
- PHPUnit for `gameform` and the web service functions.
- Language strings in `lang/en` and `lang/ja`.
- Run the code checker and moodlecheck.
- Behat scenarios in `tests/behat/manage_games.feature`, using a new data
  generator (`"mod_vocab > game instances"`): without JavaScript; with popups
  and the ⋮ menu; reordering with the keyboard *Move* dialog and dragging to
  the trash, with an accessibility check; and what students see.
- PHPUnit: `gameform_test`, `external/game_instances_test`, `renderer_test`.
- Commit to SVN and GitHub.

## Related plans

- [plan-question-checks.md](plan-question-checks.md): what games do when the
  questions or question categories they use are deleted, and Moodle 5.x
  question banks.

## Possibly later

- The same tile manager on the mobile app view (`classes/output/mobile.php`).
- Re-render tiles in place after a save, instead of reloading the page.
- A *Duplicate* item in the ⋮ menu, e.g. to use the same quiz at another stage.
- Update the title's placeholder in the popup when the stage is changed.
