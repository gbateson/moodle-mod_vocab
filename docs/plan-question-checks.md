# Plan: questions and question categories that games use

Status: 2026-11-27. Steps 1 to 4 done. Step 5 (backup and restore) is part of plan-manage-games.md.

## Goal

A game that uses questions from the question bank, such as the Quiz game,
should not break silently when those questions or their category change.
Teachers should find out, and the game should keep working where it can.
This must work in Moodle 4.x and 5.x.

## How games use questions now

- The question bank tool links each question it makes to its word, in
  `vocab_word_questions` (word id, question id).
- The Quiz game's settings (`configdata`) have a `categoryid`: one question
  category, including its subcategories, or 0 for any category in the course.
- A word is ready for the Quiz if it has at least one linked question that is
  ready (not hidden) and in the chosen categories.

## What goes wrong

| What happens | What Moodle does | Effect on the Quiz game now |
|---|---|---|
| A teacher deletes the game's category | Asks where to move its questions, moves the subcategories up to the parent, then triggers `question_category_deleted`. | `categoryid` points at nothing. No word is ready, students can't play, and *Make questions* queues tasks that fail. The settings form silently shows *Any category*. |
| A course or a 5.x question bank activity is deleted | Deletes its questions (each triggers `question_deleted`) and then its categories, **without** `question_category_deleted`. | As above, for games in other activities or courses that used those categories. |
| A teacher deletes a question | Triggers `question_deleted` after the question is gone. A question that is in use (for example, in quiz attempts) is hidden instead, and no event is triggered. | The link in `vocab_word_questions` stays. The word may lose its last question, and the game may lose all of them, with no message. |
| The category is empty, or has no questions about the activity's words | Nothing. | No word is ready, and nothing says why. |

## Decisions made

| Topic | Decision |
|---|---|
| Events or checks | Both. Events catch changes when they happen and can notify teachers. A check when the game loads catches everything else: deletions without events, course reset, restore, and direct changes to the database. |
| Events to observe | `\core\event\question_category_deleted`, `\core\event\question_deleted`, and `\core\event\course_module_deleted` for question bank activities (`qbank`, Moodle 5.x). They exist in 4.x and 5.x with the same data. |
| A deleted category | The game switches to the deleted category's parent, which still contains the deleted category's subcategories. If the parent is a top category, or is gone, the game switches to *Any category*. |
| A category that is missing at load time | The game behaves as *Any category* so students can still play, and teachers see a warning. *Make questions* uses the course's default category. |
| A deleted question | Its links to words are removed. Teachers are notified if the game had questions before and now has none. Words that lose their last question are shown on the activity page as before ("N missing"), and are not notified one by one. |
| Who is notified | Users enrolled in the course with `mod/vocab:manage` in the activity, via a new message provider, `gameproblem` (popup on by default, email permitted). |
| Activities being deleted | Are skipped: no settings changes and no messages. |
| Where problems show | On the activity page for teachers: in the *Materials* box, and as a warning icon on the game's tile. Also at the top of the game's settings form. |

## Steps

### 1. Check when a game loads — done

- `gamebase::get_problems(): array`, a list of messages for teachers. It is
  empty by default.
- Quiz game (`game/quiz/classes/game.php`):
  - **Missing category**: `categoryid` is not in `question_categories`.
    `get_category_ids()` then returns null (any category), and
    `make_materials()` uses the default category.
  - **Empty category**: the category and its subcategories have no ready
    questions.
  - **No questions about the words**: the category has questions, but none
    linked to the activity's words.
- Show the problems in `renderer::view_materials()`, on the manager tiles
  (`game_manager.mustache`), and in `gameform`.
- PHPUnit for each problem.

### 2. React to events — done

- `db/events.php` and `classes/observer.php`.
- `gamebase` gets static hooks that do nothing by default and that the Quiz
  game implements, so other games can join in later:
  - `question_category_deleted(\stdClass $category): array`: change the
    settings of the affected game instances, and return what changed;
  - `question_deleted(int $questionid, int $categoryid, array $vocabids): array`;
  - `question_categories_missing(): array`, after a question bank is deleted.
- The observer removes the deleted question's rows in `vocab_word_questions`
  first, remembering which activities used them.
- `classes/notification.php` sends the messages. Strings go in `lang/en` and
  `lang/ja`, and the provider goes in `db/messages.php`.
- PHPUnit for each event, using course-level categories (4.x), in
  `tests/observer_test.php`. The code does not assume the course context, so
  the same code serves 5.x question banks once step 3 is done.

### 3. Question banks in Moodle 5.x — done

In Moodle 5.0 and later, course question banks become question bank
activities (`mod_qbank`). Questions keep their ids but move to the activity's
context. The Quiz game and the question bank tool only looked in the course
context, so they would have found no categories and no questions.

- `classes/question_banks.php` says where a course's questions are, in 4.x
  and 5.x:
  - `get_contexts()`: the course (4.x), or its question bank activities (5.x);
  - `get_default_category()`: the course's default category (4.x), or the
    default category of the course's default question bank, which is made if
    needed (5.x);
  - `get_category_menu()`: each bank's categories, with its top category
    shown as "Top for (bank)";
  - `get_manage_url()`: the course's question categories (4.x), or its
    question banks (5.x).
- The Quiz game uses it for its *Category* menu, for *Any category*, for
  deleted questions, and for the category of questions it makes. A chosen
  category is used wherever it is, so a category in another course's bank
  still works.
- The question bank tool uses it for its parent category menu, its default
  category, its *Manage question categories* link, and its check that the
  parent category belongs to the course.
- Tests put their questions where the version keeps them, with the
  generator's `get_question_context()`, so the same tests run on 4.5 and 5.3.
- Tested on the 5.3 site (`~/Moodle/503`), including steps 1 and 2.
- Also found on 5.3:
  - Moodle 5.x refused to install mod_vocab because `vocab_supports()`
    declared the deprecated `FEATURE_GROUPMEMBERSONLY`. As in
    `mod/hotpot/lib.php`, it is now declared only when the availability API
    (`\core_availability\info`) does not exist, i.e. in Moodle 2.6 and earlier.
  - mod_vocab did not tell Moodle which questions its games use, so questions
    that students had answered in a quiz could be deleted, and their attempts
    could no longer be loaded. `mod_vocab_questions_in_use()` in `lib.php`
    now does, so Moodle hides those questions instead (and triggers no
    `question_deleted` event).

### 4. Link words to questions, not to question versions — done

Editing a question makes a new version with a new question id, but the link
stays on the old version, so the game keeps using the old text. Deleting the
old version then removes the link, even though the question still exists.

- `vocab_word_questions` gets a `questionbankentryid` field (Moodle 4.0 and
  later; 0 in Moodle 3.11), with an index. A word has at most one link to
  each entry. `questionid` stays: it is the version that was linked, and
  Moodle 3.11 still uses it.
- `progress::link_questions()` stores the entry, and does not link the same
  entry twice.
- The Quiz game uses the latest version of each entry that is ready, as
  Moodle's random questions do. If the latest version is hidden, an older
  ready version is used.
- When a version is deleted (`question_deleted`) and its entry still has
  other versions, the link moves to the latest of them, and the games are not
  told. When the last version is deleted, Moodle deletes the entry, the link
  is removed, and the games are told, as in step 2.
- Upgrade: add the field and index, fill them from `question_versions`,
  remove links to questions that no longer exist, and merge links to
  different versions of the same entry.
- PHPUnit (`tests/question_links_test.php`): editing a question, hiding the
  latest version, deleting an old version, deleting the last version, and
  the upgrade's conversion. Tested on 4.5 and 5.3.

### Later

- PHPUnit 11 (Moodle 5.x) warns that `@covers` in doc comments is
  deprecated. Move the tests to attributes, e.g.
  `#[\PHPUnit\Framework\Attributes\CoversClass(...)]`, when Moodle 4.5
  support allows.

### 5. Backup and restore

This is part of step 7 of [plan-manage-games.md](plan-manage-games.md):
map `categoryid` in `configdata` to the restored category.
