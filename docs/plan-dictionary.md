# Plan: populating the dictionary

Status: 2026-11-27. The open questions are answered. Step 1 (data model) is done, in version 2026112700.

## Goal

Fill in the information that mod_vocab can store about each word, so that
games (e.g. flashcards at the Learn stage) and the question bank tool can use
it. Most of it is generated with AI, through the AI bridge (tool_aibridge),
at the CEFR level that students need.

## What the tables hold now

The dictionary belongs to the whole site: activities only link to words
(`vocab_word_instances`).

| Information | Tables | Fields |
|---|---|---|
| Words and headwords | `vocab_words` → `vocab_lemmas` → `vocab_langs` | A form ("ran") points to its lemma, i.e. its dictionary form ("run"), which has a language code. |
| Attributes | `vocab_attributes`, `vocab_attribute_names`, `vocab_attribute_values` | Any name/value pair for a word, e.g. part of speech = noun. |
| Frequency | `vocab_frequencies`, `vocab_corpuses` | A frequency and a rank in a named corpus or word list (name, description, URL). |
| Definitions | `vocab_definitions` | Text (255 characters), with a language and a level. |
| Multimedia | `vocab_multimedia` | A media type (image, audio, video) and HTML, with a language and a level. |
| Pronunciation | `vocab_pronunciations` | A phonetic spelling (e.g. IPA) and an audio file, with a language. |
| Related words | `vocab_relationships`, `vocab_relationship_names` | Word → named relation (e.g. synonym, antonym) → word. |
| Example sentences | `vocab_samples`, `vocab_sample_words` | A sentence with a language and a level, linked to each word in it. |
| Levels | `vocab_levels`, `vocab_levelnames` | Level codes and their names in each language. |

On the 4.5 development site, only words, lemmas and one language have data.
The levels table is empty, so not even A1 to C2 exist yet.

## Terms

- **Lemma**: a word's dictionary form, the headword that you look up: "run"
  for "runs", "ran" and "running"; "good" for "better". It is not the root:
  "happy", "happiness" and "unhappy" share a root, but are three lemmas.
- **Sense**: one meaning of a word, like the numbered meanings in a
  dictionary entry. For example, "bank" (noun) is a place that keeps money, or
  the land beside a river; "bank" (verb) is to keep money in a bank. A
  definition, example sentence, picture or level belongs to one sense.

## What is missing

1. **Senses.** Definitions, samples and attributes are attached to a word
   form, with no part of speech or sense, so "bank" (money) and "bank"
   (river) cannot be told apart, and "runs" would need its own definitions.
   Proposal: attach them to a sense, i.e. lemma + part of speech + sense number.
2. **Source.** Nothing records where an item came from. Each definition,
   sample, pronunciation and media item should record its source, e.g.
   "AI: provider and prompt", "teacher", or "imported from (file)".
   `vocab_corpuses` could become a general table of sources.
3. **Review status.** AI content needs a status, e.g. draft, approved or
   rejected, so that games can choose what to use.
4. **Levels.** Add the CEFR levels A1 to C2, with their names in English and
   Japanese.
5. **Smaller points:**
   - 255 characters is enough for a definition, but perhaps not for notes.
   - Pronunciations have no accent; language codes such as `en_us` and
     `en_gb` might be enough.
   - The import tool (`tool/import`) still lists tables that do not exist,
     e.g. `vocab_word_samples` (the table is `vocab_sample_words`) and
     `vocab_ai_*`.
   - The "Edit dictionary data" tool (`tool/dictionary`) is only a stub.

## Decisions made

| Topic | Decision |
|---|---|
| Where definitions and senses come from | AI, through the AI bridge, at a chosen CEFR level. |
| Web dictionaries with CC BY-SA content (e.g. Wiktionary) | Not used for now. CC BY-SA (Creative Commons Attribution-ShareAlike) would require crediting the source wherever the content is shown, and sharing edited versions under the same licence. |
| Commercial dictionaries (e.g. Oxford, Cambridge, Merriam-Webster) | Not used: they need API keys, and their terms usually forbid keeping their content. |
| AI content | Marked as AI-generated (see *Source* above). |

## What AI could generate

- The senses of a lemma: part of speech and a short meaning for each.
- For each sense:
  - a definition at a chosen CEFR level, using simpler words than the word itself;
  - a short gloss in the students' language, e.g. Japanese;
  - example sentences at the level;
  - an estimated CEFR level, marked as an estimate;
  - related words, e.g. synonyms, antonyms, collocations and the word family.
- Pronunciation: IPA from a text model, and audio from a text-to-speech
  provider (the AI bridge already makes audio for questions).
- Pictures, with an image provider (the AI bridge already makes images for
  questions).

Output would be JSON, so that it can be checked and saved field by field.

## Proposed steps

1. **Data model:** senses, a source and a review status for each item, the
   CEFR levels, and fixes to the import tool's table names. Upgrade steps for
   existing data.
2. **AI prompts:** AI bridge templates, like the question bank tool's, for
   senses, definitions, glosses, samples and related words, with the CEFR
   level and the students' language as variables.
3. **Background tasks:** a "Fill in the dictionary" action for a word list,
   run as ad hoc tasks, like the question bank tool, showing progress. Items
   are saved with their source and status.
4. **Review and editing:** build out `tool/dictionary`, so that teachers can
   see a word's entry, approve, edit or reject AI items, and add their own.
5. **Using the data:** a Learn stage game (e.g. flashcards) that uses the
   materials mechanism (`gamebase::get_ready_words()`, `make_materials()`),
   as the quiz uses questions; definitions and samples in the question bank
   tool's prompts; and, perhaps, approved entries in backups.
6. **Optional, later:** import free frequency and level lists (e.g. NGSL or
   the CEFR-J Wordlist, licences to be checked) with the import tool.

## Any language

Vocabulary will be learned in **any** language, not only English, so nothing in
the data model or the prompts may assume English:

- Every word, definition, sample and pronunciation has a language.
- Codes are language-neutral: parts of speech use the Universal Dependencies
  tags (`NOUN`, `VERB`, ...), shown to users through language strings.
- Levels can come from any framework (CEFR, JLPT, HSK, Lexile, ...). CEFR is
  the first, and it applies to any language.
- Pronunciation records its notation: IPA, kana, pinyin, romaji, ...
- Prompts are written in English, with the vocabulary items in their own
  language, e.g. *"Create 2 CEFR-level A2 sample sentences in Japanese for the
  phrase 'すみません' in the sense of 'だれかいますか？'"*.
- The word language and the students' language are variables. Prompts are
  tuned first for English words with Japanese glosses.

## Answers to the open questions (2026-11-27)

1. **Senses:** definitions, samples, media and sense attributes belong to a
   sense, i.e. lemma + part of speech + sense number, in a table of its own.
   Frequencies, pronunciations and word-form attributes (e.g. past tense) stay
   with the word form. A word in a word list can name a sense.
2. **Review:** AI items are saved as drafts and games use them at once,
   marked as AI-generated. Rejected items are never used, and approved items
   are preferred. A site setting can limit games to approved items.
3. **Languages:** general from the start (see *Any language*). English words
   with Japanese glosses are tested first.
4. **When to fill in:** when a teacher asks, with "Fill in the dictionary" on
   a word list. Words that already have senses are skipped, so each word is
   filled in once for the whole site. Later, an activity setting could fill in
   new words automatically.
5. **Which information first:** senses, definitions, glosses, example
   sentences and the estimated level; then IPA and related words; then audio
   and pictures.

## Step 1: data model (done)

### New tables

**`vocab_senses`**: one meaning of a lemma.

| Field | Notes |
|---|---|
| `lemmaid` | The lemma. |
| `pos` | Part of speech, as a Universal Dependencies tag (`NOUN`, `VERB`, `ADJ`, `ADV`, `INTJ`, ...), or `PHRASE` for a multi-word expression. Empty if not known. |
| `sensenum` | 1, 2, 3, ... within the lemma and part of speech. |
| `meaning` | A short label to tell senses apart, e.g. "money" or "river" for "bank", or "だれかいますか？" for "すみません". |
| `langid` | The language of `meaning`; usually the lemma's own language. |
| `levelid` | The estimated level of this sense, e.g. CEFR A2. |
| `sourceid`, `status`, `timecreated`, `timemodified` | See below. |

Unique index: `lemmaid, pos, sensenum`.

**`vocab_sources`**: where items came from.

| Field | Notes |
|---|---|
| `sourcetype` | `ai`, `teacher`, `import` or `other`. |
| `name` | E.g. "OpenAI gpt-4o: Dictionary senses", or the name of an imported file. |
| `description`, `url` | Optional. |
| `providerid`, `templateid` | For AI: the AI bridge provider and prompt template. |
| `timecreated` | |

`vocab_corpuses` stays as it is, for frequency lists.

### Fields that every content item gets

These go on senses, definitions, samples, multimedia, pronunciations,
attributes and relationships:

- `sourceid`: a `vocab_sources` record, or 0 if not known.
- `status`: 0 = draft, 1 = approved, 2 = rejected. Items that teachers write
  are approved; AI items are drafts.
- `timecreated`, `timemodified`.

User IDs are not stored for now, as that would need the privacy API for
site-wide data. This can be added when the review tool is built.

### Changes to existing tables

| Table | Change |
|---|---|
| `vocab_langs` | `langcode` from 5 to 20 characters, for codes such as `es_mx_kids` or `zh_hant`. |
| `vocab_levels` | New `framework` (e.g. `CEFR`, `JLPT`) and `sortorder`. Unique index on `framework, levelcode`. |
| `vocab_definitions` | `wordid` becomes `senseid`. New `deftype`: 0 = definition (explains the meaning, usually in the word's own language, at a level), 1 = gloss (a short translation in another language). |
| `vocab_multimedia` | `wordid` becomes `senseid`. |
| `vocab_samples` | New `senseid`: the sense that the sample shows. New `originalid`: for a translation, the sample that it translates. |
| `vocab_sample_words` | No change: it lists the word forms used in each sample. For languages without spaces (e.g. Japanese), the AI returns the form used, so no word splitting is needed. |
| `vocab_pronunciations` | New `senseid` (0 = all senses), for words such as "read" or 今日 (きょう / こんにち). New `notation`: `ipa`, `kana`, `pinyin`, `romaji`, ... The language can show the variety, e.g. `en_us` or `en_gb`. |
| `vocab_attributes` | New `senseid`: 0 for a word-form attribute (e.g. tense), or a sense (e.g. gender, countable). |
| `vocab_relationships` | New `senseid` and `relsenseid` (0 = any sense). |
| `vocab_word_instances` | New `senseid` (0 = not chosen): the sense that a word in a word list means. |

### Upgrade

- Existing definitions and media are moved to a sense of their word's lemma
  (part of speech empty, sense 1), with status "approved" and an unknown
  source. A word with no lemma gets a lemma with the same spelling.
- The CEFR levels A1 to C2 are added, with names in English and Japanese
  (the names that the question bank tool already uses). New sites get them
  on install.
- The import tool's list of tables is corrected: `vocab_sample_words`, not
  `vocab_word_samples`; no `vocab_ai_*` tables; and the new tables added.

### Not in step 1

- The word list tool still adds every word as English (`$langcode = 'en'` in
  `tool/wordlist/classes/form.php`). Activity settings for the word language
  and the students' language are added in step 3, where the prompts need them.
- Backups do not yet include a word instance's sense. Restored words have no
  sense chosen. This is done with the backups in step 5.

## Open questions

None at the moment.
