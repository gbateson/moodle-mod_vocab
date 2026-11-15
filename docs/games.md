# Games in the Vocabulary activity: design

This note describes how games work in the Vocabulary activity: how students
progress through the words in a word list, how games are chosen and played,
and how a game reports what each student achieved with each word. It is the
reference for building the engine and the games themselves.

## Aims

In a Vocabulary activity, students master each word in a word list by playing
"games". Students with different kinds of intelligence and learning styles
should all be catered for, so there is a choice of games at each stage of
learning a word. Whatever game is played, the result is the same: a list of
words, with a score for each. The activity records these scores, and from
them works out how far each student has got with each word.

## Stages

Learning a word goes through four stages, in this order:

| Stage | What the student does with the word | Example games |
|---|---|---|
| Learn | Meets it: its meaning, sound, picture and use | Flashcards (image, audio, definition, example sentence) |
| Recognize | Picks it out from choices | Quiz (multiple choice), matching, space invaders |
| Recall | Brings it to mind without choices | Hangman, Wordle, jumbled letters, cloze, crossword |
| Produce | Uses it to communicate | Jumbled sentences, writing, speaking (SpeakUp), chat with an AI |

Teachers choose which stages an activity uses. Every stage is optional, but
at least one must be chosen. Stages that are not chosen are skipped.

## Progress through the stages, for each word

Each student's progress is recorded for each word and each chosen stage:

- An **interaction** is one use of the word in a game. Each interaction has a
  score from 0 to 1.
- A **successful interaction** has a score of at least the activity's
  minimum score ("Minimum attempt score", e.g. 80%).
- A word is **passed** at a stage when it has had the stage's number of
  successful interactions to pass, e.g. 1.
- A word is **completed** at a stage when it has had the stage's number of
  successful interactions to complete, e.g. 3. The number to complete is at
  least the number to pass.

Teachers set the numbers to pass and complete for each chosen stage. The
activity's existing mastery settings say how successes are counted:

- "Any attempts": every success counts.
- "Most recent attempts": only the successes among the most recent
  interactions count.
- "Consecutive attempts": a failed interaction starts the count again.

### Which words a stage offers

A word is **available at a stage** when:

1. it has been passed at every earlier chosen stage, and
2. it has not been completed at this stage.

So a word can be passed at "recognize", and then be practised at "recall",
while it is still being completed at "recognize". When a game is played, it
is given only words that are available at its stage. When every word has been
completed at a stage, that stage's games are no longer offered (their buttons
are shown, but disabled).

### Spaced repetition

Teachers can turn spaced repetition on with the activity's "Minimum delay
between attempts" setting:

- None: successes count whenever they happen.
- Fixed delay: a success counts only if the given time has passed since the
  word's last counted success at that stage.
- Expanding delay: as fixed, but the delay grows with each counted success,
  e.g. 1 day, 2 days, 4 days. The delays are shortened if necessary, so that
  the words can still be completed before the activity closes ("Games close").

A word that is not yet due can still be played, if a game needs more words,
but its interactions do not count towards passing or completing.

### Mastery and the results chart

A word is **mastered** when it has been completed at every chosen stage. The
results chart on the activity's page shows, for each student:

- Completed: mastered words.
- In progress: words that have had at least one interaction.
- Not started: the other words.

The word list shows, for each word, a mark for each chosen stage: empty (not
started), half (passed) or full (completed).

The activity's grade is the percentage of words mastered, or of the
"Minimum word count", if it is set. If "Include partially completed words" is
on, words in progress count as a fraction, by the stages they have completed.

## Games

### Game plugins

Each game is a subplugin of the Vocabulary activity, of the type `vocabgame`,
in `mod/vocab/game/NAME`. A game says:

- which stages it can be used at,
- what materials it needs for a word, e.g. questions, an image, audio or an
  example sentence, and whether a word has them,
- how to make missing materials, if it can (in the background), and
- its own settings for an activity, e.g. the question category for a quiz.

A game is given one or more words by the activity, and uses them to find or
make its study materials. When an attempt is finished, it gives the activity a
list of words with a score from 0 to 1 for each, and the activity does the
rest (see "Recording results").

Games can be made in different ways, and the activity does not mind which:

- As Moodle questions, using the question engine: e.g. the quiz, matching,
  cloze, ordering and SpeakUp. Questions with several parts (e.g. matching)
  give a score for each part, so each part's word gets its own score.
- As games in the browser (JavaScript): e.g. Hangman, Wordle and space
  invaders. Each round is set up by the server, and the scores that are sent
  back are checked against it, e.g. only words in the round can be scored.
- With AI, through the AI bridge (tool_aibridge): e.g. a monologue or a chat
  with an AI. The AI is asked to return a score for each target word.

### Games in an activity

Teachers choose which games an activity offers, at which stage, and in which
order, with each game's own settings. Each choice is a "game instance". The
same game can be used at more than one stage, e.g. a quiz of recognition
questions and a quiz of recall questions, as two instances.

On the activity's page, students see a button for each game instance:

- enabled, if the game has words available at its stage, with the materials
  that it needs;
- disabled, with the reason, otherwise: the stage is not unlocked yet (no
  word has passed the earlier stages), all its words are completed, or the
  materials are not ready.

Students choose which game to play. An attempt that is not finished can be
resumed.

### Choosing the words for an attempt

When an attempt starts, the activity chooses its words from those available
at the game's stage, with the materials that the game needs, in this order:

1. words that are due (with spaced repetition),
2. words in progress at this stage, least recently played first,
3. words not yet started at this stage, in word list order,
4. if the game needs more words, words that are not due.

The game says how many words it wants, e.g. five words for a quiz.

### Recording results

When an attempt is finished, the activity:

1. records which words were used (`vocab_word_usages`), and the score for
   each (`vocab_word_attempts`),
2. updates each word's progress at the attempt's stage (`vocab_word_stages`):
   interactions, successes, passed and completed,
3. updates each word's overall state (`vocab_word_states`): not started, in
   progress or completed (mastered), and
4. updates the student's grade.

Games never write to these tables themselves.

### Materials

Games need materials for each word, e.g. the quiz needs a question about the
word. Many materials may exist already, e.g. questions made earlier with the
question bank tool.

- When the question bank tool makes questions, it records which word each
  question is about (`vocab_word_questions`). The questions that it made
  earlier are linked to their words when the activity is upgraded.
- Teachers see whether each game is ready, on the activity's page, e.g.
  "Quiz (Recognize): 18 of 24 words have materials, 2 being made, 4 missing",
  and can ask for the missing materials to be made in the background, with
  the AI bridge. The background tasks run when Moodle's cron runs.
- A game is only given words for which it has materials.

A game says what it can do about materials with these methods of
`\mod_vocab\gamebase`: `get_ready_words()`, `can_make_materials()`,
`get_pending_words()` and `make_materials()`. The quiz makes questions with the
question bank tool, as its settings say: the prompt, question type, level and
number of questions for each word. They are put in the quiz's category.

## Database

New and changed tables:

| Table | Change | Holds |
|---|---|---|
| `vocab_stages` | new | The chosen stages of an activity, with the numbers to pass and complete |
| `vocab_game_instances` | add `stage` | The stage at which a game is used |
| `vocab_game_attempts` | add `stage`, `uniqueid`, `timemodified` | The attempt's stage, and its question usage (for question games) |
| `vocab_word_attempts` | add `timecreated` | When the word was scored |
| `vocab_word_stages` | new | Each student's progress with each word at each stage |
| `vocab_word_questions` | new | Which questions are about which words |

## Plan

1. This design note.
2. The engine: stages and their settings, progress for each word and stage,
   choosing words, recording results, the results chart and word list, the
   game buttons, and the `vocabgame` subplugin type.
3. The first game: a quiz of questions about the words, from the question
   bank (e.g. questions made by the question bank tool), at the recognize or
   recall stage.
4. Making missing materials in the background (done for the quiz).
5. Then more games, one at a time, e.g. flashcards (learn), Hangman (recall)
   and a chat with an AI (produce).
