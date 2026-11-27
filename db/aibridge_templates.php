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
 * Prompts and output formats that the Vocabulary activity adds to the
 * library of the AI bridge (tool_aibridge):
 *
 * - for generating TOEIC-style questions with the question bank tool. They
 *   extend the bridge's own "Generate GIFT/XML questions" prompt.
 * - for filling in the dictionary (see docs/plan-dictionary.md). They extend
 *   "dictionary-entry", and reply in JSON. They work for any language: the
 *   prompts are in English, and the vocabulary items in their own language.
 *
 * After changing them, increase the plugin version and call
 * \tool_aibridge\library::sync_component('mod_vocab') in db/upgrade.php.
 *
 * @package    mod_vocab
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The templates are text for an AI, in which line breaks matter, so their lines are long.
// phpcs:disable moodle.Files.LineLength

$templates = [

    // Formats.
    'toeic-part1-format' => [
        'type' => 'format',
        'name' => 'TOEIC R&L (Part 1): Describe an image',
        'qformat' => 'gift',
        'body' => <<<'EOD'
::question-name::question-text [[IMAGE image-prompt]] {
    =[[AUDIO correct-answer-audio]] #feedback-for-correct-answer
    ~[[AUDIO wrong-answer-audio-1]] #feedback-for-wrong-answer-1
    ~[[AUDIO wrong-answer-audio-2]] #feedback-for-wrong-answer-2
    ~[[AUDIO wrong-answer-audio-3]] #feedback-for-wrong-answer-3
}
EOD
,
        'instructions' => <<<'EOD'
The "question-text" is always the following short text (including the HTML tags):
<p>Select the statement that best describes the photograph.</p>

{{include:tool_aibridge:image-prompt-rules}}

The "correct-answer-audio", "wrong-answer-audio-1", "wrong-answer-audio-2", and "wrong-answer-audio-3" are text strings that are suitable for sending as input to an AI audio generator such as TTS by OpenAI. They are enclosed in double square brackets, as in the following examples:

=[[AUDIO They are talking about a broken machine.]]
~[[AUDIO They are taking time off to go shopping.]]
~[[AUDIO They are walking to work in a city.]]
~[[AUDIO They are working in a factory.]]

The "feedback-for-correct-answer" item explains why the answer is correct. The "feedback-for-wrong-answer-1", "feedback-for-wrong-answer-2" and "feedback-for-wrong-answer-3" items explain why the respective answer is wrong and give a hint about the correct answer.
{{include:tool_aibridge:multilang-feedback}}
EOD
,
    ],
    'toeic-part2-format' => [
        'type' => 'format',
        'name' => 'TOEIC R&L (Part 2): Question and response',
        'qformat' => 'gift',
        'body' => <<<'EOD'
::question-name::[[AUDIO question-audio]] {
    =[[AUDIO correct-answer-audio]] #feedback-for-correct-answer
    ~[[AUDIO wrong-answer-audio-1]] #feedback-for-wrong-answer-1
    ~[[AUDIO wrong-answer-audio-2]] #feedback-for-wrong-answer-2
}
EOD
,
        'instructions' => <<<'EOD'
The "question-audio", "correct-answer-audio", "wrong-answer-audio-1" and "wrong-answer-audio-2" are text strings that are suitable for sending as input to an AI audio generator such as TTS by OpenAI. They are enclosed in double square brackets.

The "feedback-for-correct-answer" item explains why the answer is correct. The "feedback-for-wrong-answer-1" and "feedback-for-wrong-answer-2" items explain why the respective answer is wrong and give a hint about the correct answer.
{{include:tool_aibridge:multilang-feedback}}
EOD
,
        'examples' => <<<'EOD'
Here are two example questions:

::Part-2.Q1::[[AUDIO Who should I give the report to?]] {
    =[[AUDIO Anyone in accounting.]] #<span lang="en" class="multilang">Correct! The question starts with 'Who', and this answer is the only one that refers to a person. </span><span lang="ja" class="multilang">正解です！質問は「Who」で始まっています。この答えだけが人を指しています。</span>
    ~[[AUDIO No, not this time.]] #<span lang="en" class="multilang">Oops, wrong answer. The question is not a YES-NO question. Listen carefully to the first word of the question. </span><span lang="ja" class="multilang">残念、不正解です。この質問はYES-NOの質問ではありません。質問の最初の単語を注意深く聞いてください。</span>
    ~[[AUDIO By next week.]] #<span lang="en" class="multilang">Oops, wrong answer. The question is not about time. Listen carefully to the first word of the question.</span><span lang="ja" class="multilang">残念、不正解です。この質問は時間についてではありません。質問の最初の単語を注意深く聞いてください。</span>
}

::Part-2.Q2::[[AUDIO Would you like to interview applicants yourself or shall I?]] {
    =[[AUDIO I'd be happy to.]] #<span lang="en" class="multilang">Correct! This is the only answer that selects one of the two alternatives being offered in the question.</span><span lang="ja" class="multilang">正解です！この答えだけが質問で提示された2つの選択肢のうちの1つを選んでいます。</span>
    ~[[AUDIO Yes, thank you.]] #<span lang="en" class="multilang">Oops, wrong answer. This answer is not suitable because the question is not a simple YES/NO question. The question is offering a choice about who should conduct an interview. </span><span lang="ja" class="multilang">残念、不正解です。この答えは適切ではありません。この質問は単純なYES/NOの質問ではありません。誰がインタビューを行うべきかという選択を提示しています。</span>
    ~[[AUDIO More than 100 people.]] #<span lang="en" class="multilang">Oops, wrong answer. This answer does not specify either of the two options being offered in the question. The question is offering a choice about who should conduct an interview. </span><span lang="ja" class="multilang">残念、不正解です。この答えは質問で提示された2つの選択肢のどちらかを具体的に指定していません。質問は誰がインタビューを行うべきかという選択を提示しています。</span>
}
EOD
,
    ],
    'toeic-part3-format' => [
        'type' => 'format',
        'name' => 'TOEIC R&L (Part 3): Conversations',
        'qformat' => 'multianswer',
        'body' => <<<'EOD'
<div class="conversation-question" style="max-width: 640px;"><div class="border rounded my-2 p-2 audio" style="background-color: #f8fff8;"><p class="my-0">[[AUDIO class="nomediaplugin" conversation-audio]]</p><p class="my-0 ml-3"><small><span class="multilang" lang="en">Press the playback button to hear the audio.</span><span class="multilang" lang="ja">再生ボタンを押すと音声が流れます。</span></small></p></div><div class="border rounded mt-4 p-2 question-a" style="background-color: #f8fff8;"><p class="my-0 question-text"><b class="mr-1">A.</b><span class="mr-1">question-text-A</span></p><p class="my-0 question-audio">
            [[AUDIO class="nomediaplugin" question-text-A]]
        </p><div class="ml-3 question-answers">
        {1:MCVS:=correct-answer-A #feedback-for-correct-answer-A
            ~wrong-answer-A.1 #feedback-for-wrong-answer-A.1
            ~wrong-answer-A.2 #feedback-for-wrong-answer-A.2
            ~wrong-answer-A.3 #feedback-for-wrong-answer-A.3
        }
        </div></div><div class="border rounded mt-4 p-2 question-b" style="background-color: #f8fff8;"><p class="my-0 question-text"><b class="mr-1">B.</b><span class="mr-1">question-text-B</span></p><p class="my-0 question-audio">
            [[AUDIO class="nomediaplugin" question-text-B]]
        </p><div class="ml-3 question-answers">
        {1:MCVS:=correct-answer-B #feedback-for-correct-answer-B
            ~wrong-answer-B.1 #feedback-for-wrong-answer-B.1
            ~wrong-answer-B.2 #feedback-for-wrong-answer-B.2
            ~wrong-answer-B.3 #feedback-for-wrong-answer-B.3
        }
        </div></div><div class="border rounded mt-4 p-2 question-c" style="background-color: #f8fff8;"><p class="my-0 question-text"><b class="mr-1">C.</b><span class="mr-1">question-text-C</span></p><p class="my-0 question-audio">
            [[AUDIO class="nomediaplugin" question-text-C]]
        </p><div class="ml-3 question-answers">
        {1:MCVS:=correct-answer-C #feedback-for-correct-answer-C
            ~wrong-answer-C.1 #feedback-for-wrong-answer-C.1
            ~wrong-answer-C.2 #feedback-for-wrong-answer-C.2
            ~wrong-answer-C.3 #feedback-for-wrong-answer-C.3
        }
        </div></div></div>
EOD
,
        'instructions' => <<<'EOD'
The "conversation-audio" is made up of a "header" and a series of "utterances". The header describes the gender and role of each participant. The utterances are a series of lines that are suitable for sending as a prompt to an AI audio generator such as TTS by OpenAI. Each utterance starts with a single letter and colon to identify the speaker. This is followed by the utterance of that speaker. The whole conversation is enclosed in double square brackets.

The items called "question-text-A", "question-text-B" and "question-text-C" are each a text string containing a comprehension question about the contents of the conversation. The text will appear as text on a computer screen and will also be converted to audio using an AI audio generator such as TTS.

The items whose name begins with "correct-answer" or "wrong-answer" are each a string containing an answer to the question. The strings will be displayed on the computer screen.

The items whose names begin with "feedback-for-correct-answer" each explain why their corresponding correct answer is correct. The items whose names begin with "feedback-for-wrong-answer" each explain why their respective answer is wrong and give a hint about the correct answer.
{{include:tool_aibridge:multilang-feedback}}
EOD
,
        'examples' => <<<'EOD'
Here is a sample conversation and questions with multilingual feedback:

<div class="conversation-question" style="max-width: 640px;"><div class="border rounded my-2 p-2 audio" style="background-color: #f8fff8;"><p class="my-0">[[AUDIO class="nomediaplugin"
            A=woman (customer calling with complaint)
            B=male (staff on a customer help line)
            A: Hello. I'm calling about a coffee machine I purchased from your website. It has stopped working, even though I haven't had it for very long. I expected it to last much longer than this.
            B: Oh, I'm sorry to hear that. Our warranty covers products or up to a year. Do you know when you bought it?
            A: I've had it for over a year so the warranty has probably just expired. This is so disappointing.
            B: Well, I'll tell you what we can do. Although we can't replace it, since you're a valued customer, I can offer you a coupon for 40% off your next purchase.
        ]]</p><p class="my-0 ml-3"><small><span class="multilang" lang="en">Press the playback button to hear the audio.</span><span class="multilang" lang="ja">再生ボタンを押すと音声が流れます。</span></small></p></div><div class="border rounded mt-4 p-2 question-a" style="background-color: #f8fff8;"><p class="my-0 question-text"><b class="mr-1">A.</b><span class="mr-1">Why is the woman calling?</span></p><p class="my-0 question-audio">
            [[AUDIO class="nomediaplugin" Why is the woman calling?]]
        </p><div class="ml-3 question-answers">
        {1:MCVS:=To complain about a product #<span class="multilang" lang="en">That's correct! The woman is not satisfied with the coffee machine that she purchased.</span><span class="multilang" lang="ja">正解です！女性は購入したコーヒーメーカーに満足していない。</span>
            ~To cancel an order #<span class="multilang" lang="en">Oops, wrong answer. The woman received the product a long time ago, so she cannot cancel. Listen carefully for why the woman is disappointed.</span><span class="multilang" lang="ja">間違った答えだ。女性が商品を受け取ったのはずいぶん前のことなので、キャンセルはできない。女性がなぜがっかりしているのか、よく聞いてください。</span>
            ~To redeem a gift card #<span class="multilang" lang="en">Oops, wrong answer. The woman does not mention a gift card. Listen carefully for why the woman is disappointed.</span><span class="multilang" lang="ja">間違った答えだ。女性はギフトカードには触れていない。女性がなぜがっかりしているのか、よく聞いてください。</span>
            ~To renew a warranty #<span class="multilang" lang="en">Oops, wrong answer. The warranty is mentioned, but only because it has expired. Listen carefully for why the woman is disappointed.</span><span class="multilang" lang="ja">間違った答えだ。保証の話は出てくるが、それは期限が切れたからに過ぎない。女性がなぜがっかりしているのか、よく聞いてください。</span>
        }
        </div></div><div class="border rounded mt-4 p-2 question-b" style="background-color: #f8fff8;"><p class="my-0 question-text"><b class="mr-1">B.</b><span class="mr-1">What does the man ask the woman about?</span></p><p class="my-0 question-audio">
            [[AUDIO class="nomediaplugin" What does the man ask the woman about?]]
        </p><div class="ml-3 question-answers">
        {1:MCVS:=A date of purchase #<span class="multilang" lang="en">That's correct! The man asked the woman when she bought the coffee machine.</span><span class="multilang" lang="ja">正解だ！男は女にコーヒーメーカーを買ったのはいつかと尋ねた。</span>
            ~A model name #<span class="multilang" lang="en">Oops, wrong answer. Listen carefully to "wh" word in the question that the man asks the woman. </span><span class="multilang" lang="ja">おっと、間違った答えだ。男が女に尋ねる質問の中の 「wh 」という単語に注意深く耳を傾けてください。</span>
            ~A brand of coffee #<span class="multilang" lang="en">Oops, wrong answer. The man does not ask about a brand. He asks a "when" question, so the correct response must be related to time.</span><span class="multilang" lang="ja">間違った答えだ。この男はブランドについて尋ねていない。彼は 「いつ 」という質問をしているのだから、正しい答えは時間に関するものでなければならない。</span>
            ~A catalog number #<span class="multilang" lang="en">Oops, wrong answer. A catalog number is not mentioned in this conversation. Listen carefully to the question that starts "Do you know ...".</span><span class="multilang" lang="ja">間違った答えだ。この会話にはカタログ番号は出てこない。Do you know ...」で始まる質問に注意深く耳を傾けてください。</span>
        }
        </div></div><div class="border rounded mt-4 p-2 question-c" style="background-color: #f8fff8;"><p class="my-0 question-text"><b class="mr-1">C.</b><span class="mr-1">What does the man offer to do?</span></p><p class="my-0 question-audio">
            [[AUDIO class="nomediaplugin" What does the man offer to do?]]
        </p><div class="ml-3 question-answers">
        {1:MCVS:=Provide a discount #<span class="multilang" lang="en">That's correct! He offers the woman a coupon for 40% off her next purchase.</span><span class="multilang" lang="ja">正解だ！彼は女性に次回の買い物が40％オフになるクーポンを差し出す。</span>
            ~Send a free sample #<span class="multilang" lang="en">Oops, wrong answer. Listen carefully to the offer at the end of the conversation.</span><span class="multilang" lang="ja">間違った答えだ。会話の最後の申し出をよく聞いてください。</span>
            ~Extend a warranty #<span class="multilang" lang="en">Oops, wrong answer. Listen carefully to the offer at the end of the conversation.</span><span class="multilang" lang="ja">間違った答えだ。会話の最後の申し出をよく聞いてください。</span>
            ~Issue a refund #<span class="multilang" lang="en">Oops, wrong answer. They do not talk about a refund. Listen carefully to the offer at the end of the conversation.</span><span class="multilang" lang="ja">間違った答えだ。彼らは返金については話していない。会話の最後の申し出をよく聞いてください。</span>
        }
        </div></div></div>
EOD
,
    ],
    'toeic-part5-format' => [
        'type' => 'format',
        'name' => 'TOEIC R&L (Part 5): Incomplete sentences',
        'qformat' => 'gift',
        'body' => <<<'EOD'
::question-name::sentence-with-blank {
    =target-word #feedback-for-correct-answer
    ~distractor-1 #feedback-for-wrong-answer-1
    ~distractor-2 #feedback-for-wrong-answer-2
    ~distractor-3 #feedback-for-wrong-answer-3
}
EOD
,
        'instructions' => <<<'EOD'
The "sentence-with-blank" is the sample sentence, with the target word replaced by a blank, written as five underscores: _____

The "feedback-for-correct-answer" item explains why the answer is correct. The "feedback-for-wrong-answer-1", "feedback-for-wrong-answer-2" and "feedback-for-wrong-answer-3" items explain why the respective answer is wrong and give a hint about the correct answer.
{{include:tool_aibridge:multilang-feedback}}
EOD
,
    ],

    // Prompts.
    'toeic-part1' => [
        'type' => 'prompt',
        'name' => 'TOEIC R&L (Part 1): Describe an image',
        'extends' => 'tool_aibridge:generate-questions',
        'body' => '{{block:description}}' . <<<'EOD'
These questions assess a test-taker's ability to match spoken English sentences to visual information. Each question presents a black-and-white photograph of a scene, often depicting people engaged in everyday activities, workplace settings, or general situations.

The narrator reads four sentences aloud, and the test-taker must select the one sentence that accurately describes something in the photograph. Only one sentence is correct.

The correct sentence provides an accurate description of the scene. It may describe [1] an action being performed by a person (e.g., "A woman is typing on a computer."), or [2] the location of objects or people (e.g., "There are books on the shelf."), or [3] the general situation or condition (e.g., "The room is empty.").

The other three sentences, known as distractors, are incorrect in meaning but are grammatically correct and syntactically similar to the correct answer. Distractors often include [1] incorrect nouns or verbs that misrepresent the action or situation (e.g., "A woman is writing a letter" instead of "typing on a computer."), or [2] misleading prepositions or conditions (e.g., "There are books under the shelf" instead of "on the shelf."), or [3] plausible but false descriptions of people, objects, or locations.

These questions are designed to evaluate a test-taker's ability to: [1] comprehend spoken English; [2] analyze visual information and match it to a spoken description; [3] identify subtle errors in meaning, vocabulary, or prepositions within sentences.
EOD . '{{endblock}}',
        'formatlinks' => ['multichoice' => 'toeic-part1-format'],
    ],
    'toeic-part2' => [
        'type' => 'prompt',
        'name' => 'TOEIC R&L (Part 2): Question and response',
        'extends' => 'tool_aibridge:generate-questions',
        'body' => '{{block:description}}' . <<<'EOD'
These questions assess a test-taker's ability to comprehend spoken English in short exchanges. Each question consists of a spoken sentence (e.g., a question, statement, or request) followed by three spoken responses. The test-taker must choose the one response that is the most appropriate or logical reply.

The correct response logically answers or corresponds to the spoken sentence. It may [1] directly answer a question (e.g., "What time does the meeting start?" → "It starts at three o’clock."), or [2] acknowledge a statement or request (e.g., "Please send me the file." → "I’ll do it right away.").

The other two responses are incorrect because they fail to match the spoken sentence logically. They may include [1] responses that sound similar but are unrelated (e.g., "What time does the meeting start?" → "Yes, I met her yesterday."), or [2] responses that contain correct vocabulary but are contextually inappropriate (e.g., "Please send me the file." → "It’s a very important document."), or [3] responses that use similar sounds (e.g., "How did you pay?" → "I’m on my way.").

These questions evaluate the test-taker’s ability to [1] understand spoken English in short, conversational sentences, [2] recognize appropriate responses based on logic, context, and meaning, and [3] differentiate between similar sounds or vocabulary that can lead to confusion.
EOD . '{{endblock}}',
        'formatlinks' => ['multichoice' => 'toeic-part2-format'],
    ],
    'toeic-part3' => [
        'type' => 'prompt',
        'name' => 'TOEIC R&L (Part 3): Conversations',
        'extends' => 'tool_aibridge:generate-questions',
        'body' => '{{block:task}}' . <<<'EOD'
Create {{number-of-questions}} {{type-of-questions}} question sets to check students' understanding of the vocabulary item "{{vocabulary-item}}".
Use only CEFR level {{level-of-questions}} {{target-language}}.
EOD . '{{endblock}}' .
            '{{block:description}}' . <<<'EOD'
These question sets assess a test-taker's ability to comprehend spoken conversations in English. Each question set is based on a recorded dialogue between two or more speakers, typically in workplace or everyday settings. The conversation is played once, and the test-taker must answer three questions about it.

The conversations are short, typically 4-5 exchanges between speakers. They often involve [1] workplace scenarios (e.g., scheduling a meeting, discussing a task), or [2] customer service situations (e.g., placing an order, resolving a complaint), or [3] everyday topics (e.g., making travel plans, giving directions).

For each conversation, there are three multiple-choice questions, each with four answer choices. Typically, the questions ask the test-taker to [1] identify specific details (e.g., "What time will the meeting start?"), or [2] determine the purpose of the conversation (e.g., "Why is the man calling?"), or [3] infer the speaker’s intention, opinion, or next step (e.g., "What will the woman probably do next?").

Incorrect answers are plausible but fail to match the information in the conversation. Distractors commonly [1] include details not mentioned or irrelevant to the question, or [2] use words or phrases from the conversation but misrepresent the meaning, or [3] confuse similar-sounding words or ideas.

These questions evaluate the test-taker’s ability to [1] understand spoken English in natural, multi-speaker conversations, and [2] identify both explicit details and implied information, and [3] recognize the purpose, context, and flow of spoken exchanges.
EOD . '{{endblock}}',
        'formatlinks' => ['multichoice' => 'toeic-part3-format', 'multianswer' => 'toeic-part3-format'],
    ],
    'toeic-part5' => [
        'type' => 'prompt',
        'name' => 'TOEIC R&L (Part 5): Incomplete sentences',
        'extends' => 'tool_aibridge:generate-questions',
        'body' => '{{block:description}}' . <<<'EOD'
The question text is a sample sentence containing a blank where the target word should be.
There is only one blank in the sentence. It may be at the beginning, in the middle, or at the end of the sentence.
There are four answers. The first answer is the target word. It is the correct answer.
The other answers, i.e. answers two, three and four, are all incorrect.
They are called distractors. The distractors are all similar in some way to the correct answer.
For example, they might have a similar spelling to the target word or they might be the same part of speech.
However all the distractors are clearly incorrect because they are grammatically incorrect, or have an incorrect meaning, or do not form an idiom or collocation.
EOD . '{{endblock}}',
        'formatlinks' => ['multichoice' => 'toeic-part5-format'],
    ],

    // Dictionary: fragments.
    'dictionary-json-rules' => [
        'type' => 'fragment',
        'name' => 'Dictionary: JSON output rules',
        'description' => 'Tells the AI to reply with JSON only, so that the dictionary items can be checked and saved.',
        'body' => <<<'EOD'
Follow these important instructions:
[1] In your response, reply with JSON only. Do not include any greetings, explanations or platitudes.
[2] In your response, do not use any markdown, such as code fences (backticks), headings (repeated sharp signs), or bold and italic text (asterisks or underlines).
[3] Use the JSON structure below. Keep the field names exactly as they are, in English, and replace each placeholder value with your own text.
[4] Write each value in the language that its description gives. Never translate the vocabulary item itself.
EOD
,
    ],
    'dictionary-pos-tags' => [
        'type' => 'fragment',
        'name' => 'Dictionary: parts of speech',
        'description' => 'The Universal Dependencies tags for parts of speech, which work for any language.',
        'body' => <<<'EOD'
The "part-of-speech" is one of these Universal Dependencies tags: ADJ (adjective), ADP (adposition, i.e. a preposition or postposition), ADV (adverb), AUX (auxiliary), CCONJ (coordinating conjunction), DET (determiner), INTJ (interjection), NOUN (noun), NUM (numeral), PART (particle), PRON (pronoun), PROPN (proper noun), SCONJ (subordinating conjunction) or VERB (verb). For a multi-word expression or set phrase that has no single part of speech, use PHRASE.
EOD
,
    ],
    'dictionary-definition-rules' => [
        'type' => 'fragment',
        'name' => 'Dictionary: definitions and glosses',
        'description' => 'How to write a definition and a gloss for one sense of a vocabulary item.',
        'body' => <<<'EOD'
The "definition-at-level" explains the meaning of the sense in {{level-framework}} level {{level}} {{word-language}}, using words that are easier than the vocabulary item itself. It is one sentence or phrase, of less than 200 characters, and does not contain the vocabulary item.

The "gloss-in-students-language" is a short translation of the sense into {{gloss-language}}, of one to five words, that a student could write next to the vocabulary item.
EOD
,
    ],
    'dictionary-sample-rules' => [
        'type' => 'fragment',
        'name' => 'Dictionary: sample sentences',
        'description' => 'How to write sample sentences for one sense of a vocabulary item.',
        'body' => <<<'EOD'
Each "sample-sentence" is a short, natural sentence in {{level-framework}} level {{level}} {{word-language}}, which shows the meaning of the sense clearly. It contains the vocabulary item, perhaps in another form, e.g. "ran" or "running" for "run". Each sample sentence uses the vocabulary item in a different situation.

The "form-used-in-sentence" is the vocabulary item exactly as it appears in the sample sentence, e.g. "ran" in "She ran to the station."

The "translation-of-sentence" is a natural translation of the sample sentence into {{gloss-language}}.
EOD
,
    ],

    // Dictionary: formats.
    'dictionary-entry-format' => [
        'type' => 'format',
        'name' => 'Dictionary: entry (JSON)',
        'qformat' => 'json',
        'body' => <<<'EOD'
{
    "lemma": "dictionary-form",
    "senses": [
        {
            "pos": "part-of-speech",
            "meaning": "short-meaning",
            "level": "estimated-level",
            "definition": "definition-at-level",
            "gloss": "gloss-in-students-language",
            "samples": [
                {
                    "sentence": "sample-sentence",
                    "form": "form-used-in-sentence",
                    "translation": "translation-of-sentence"
                }
            ]
        }
    ]
}
EOD
,
        'instructions' => <<<'EOD'
The "dictionary-form" is the form of the vocabulary item that a dictionary lists it under, e.g. "run" for "ran", or "good" for "better". For a phrase, it is usually the phrase itself.

The "senses" list has one item for each sense, from the most common to the least common.

{{include:mod_vocab:dictionary-pos-tags}}

The "short-meaning" is a few words in {{word-language}} that tell this sense apart from the other senses, e.g. "money" and "river" for the two senses of the English noun "bank".

The "estimated-level" is the {{level-framework}} level at which students usually learn this sense, e.g. A1 or B2.

{{include:mod_vocab:dictionary-definition-rules}}

The "samples" list has {{number-of-samples}} items for each sense.

{{include:mod_vocab:dictionary-sample-rules}}
EOD
,
        'examples' => <<<'EOD'
This example only shows the structure of the JSON. It is an entry for the Japanese vocabulary item "すみません", at CEFR level A1, with glosses in English and one sample sentence for each sense. Never copy its meanings, definitions, glosses or sample sentences into your reply, not even for the same vocabulary item: write your own.
{
    "lemma": "すみません",
    "senses": [
        {
            "pos": "INTJ",
            "meaning": "あやまるとき",
            "level": "A1",
            "definition": "人に悪いことをしたときに言うことば。",
            "gloss": "I'm sorry",
            "samples": [
                {
                    "sentence": "おそくなって、すみません。",
                    "form": "すみません",
                    "translation": "I'm sorry I'm late."
                }
            ]
        },
        {
            "pos": "INTJ",
            "meaning": "人をよぶとき",
            "level": "A1",
            "definition": "店などで人をよぶときに言うことば。",
            "gloss": "Excuse me",
            "samples": [
                {
                    "sentence": "すみません、だれかいますか？",
                    "form": "すみません",
                    "translation": "Excuse me, is anyone here?"
                }
            ]
        }
    ]
}
EOD
,
    ],
    'dictionary-definition-format' => [
        'type' => 'format',
        'name' => 'Dictionary: definition and gloss (JSON)',
        'qformat' => 'json',
        'body' => <<<'EOD'
{
    "definition": "definition-at-level",
    "gloss": "gloss-in-students-language"
}
EOD
,
        'instructions' => '{{include:mod_vocab:dictionary-definition-rules}}',
    ],
    'dictionary-samples-format' => [
        'type' => 'format',
        'name' => 'Dictionary: sample sentences (JSON)',
        'qformat' => 'json',
        'body' => <<<'EOD'
[
    {
        "sentence": "sample-sentence",
        "form": "form-used-in-sentence",
        "translation": "translation-of-sentence"
    }
]
EOD
,
        'instructions' => "The list has {{number-of-samples}} items.\n\n{{include:mod_vocab:dictionary-sample-rules}}",
    ],
    'dictionary-related-format' => [
        'type' => 'format',
        'name' => 'Dictionary: related words (JSON)',
        'qformat' => 'json',
        'body' => <<<'EOD'
[
    {
        "relation": "relation-name",
        "item": "related-item",
        "pos": "part-of-speech",
        "meaning": "short-meaning"
    }
]
EOD
,
        'instructions' => <<<'EOD'
The "relation-name" is one of these: synonym (a word or phrase with the same meaning in this sense), antonym (a word or phrase with the opposite meaning), collocation (a phrase in which the vocabulary item is often used, e.g. "make a decision" for "decision") or family (a word with the same root but a different part of speech, e.g. "decide" for "decision"). Give at most {{number-of-related}} items for each relation, and leave out a relation if there are no good items for it.

The "related-item" is the dictionary form of the related word or phrase, in {{word-language}}. For a collocation, it is the whole phrase, including the vocabulary item.

{{include:mod_vocab:dictionary-pos-tags}} It is the part of speech of the related item, not of the vocabulary item.

The "short-meaning" is a few words in {{word-language}} that give the meaning of the related item.
EOD
,
    ],

    // Dictionary: prompts. They all extend "dictionary-entry", so that they are listed
    // for the dictionary, and not for the question bank tool.
    'dictionary-entry' => [
        'type' => 'prompt',
        'name' => 'Dictionary: entry (senses, definitions, glosses and sample sentences)',
        'description' => 'A learner\'s dictionary entry for one vocabulary item, in any language. Other dictionary prompts extend this one.',
        'body' => <<<'EOD'
{{block:role}}I am a language teacher. I teach {{word-language}} to students whose first language is {{gloss-language}}.
Your role is an expert lexicographer who writes learner's dictionaries.
Help me to write dictionary content that I can save in a Moodle LMS.{{endblock}}

{{block:task}}Write a learner's dictionary entry for the {{word-language}} vocabulary item "{{vocabulary-item}}".
Give each of its common senses, up to {{number-of-senses}} senses. Many vocabulary items have only one common sense: give a second or third sense only if it is a clearly different meaning that a dictionary would list separately, like the two senses of the English noun "bank". For each sense, write a definition, a gloss in {{gloss-language}}, and {{number-of-samples}} sample sentences.
Use only {{level-framework}} level {{level}} {{word-language}} in the definitions and sample sentences.{{endblock}}

{{block:description}}A sense is one meaning of the vocabulary item, like one of the numbered meanings in a dictionary entry. For example, the English noun "bank" has two common senses: a place that keeps money, and the land beside a river. The same word with a different part of speech is a different sense, e.g. the English verb "bank", meaning to keep money in a bank. Give only senses that a {{level-framework}} level {{level}} student might need, and leave out rare and technical senses. Never make up senses, or split one meaning into two senses, to reach the number of senses.{{endblock}}

{{block:rules}}{{include:mod_vocab:dictionary-json-rules}}{{endblock}}

{{output-format}}
EOD
,
        'parameters' => [
            ['name' => 'word-language', 'default' => 'English', 'required' => true,
                'description' => 'The language of the vocabulary item, i.e. the language that the students are learning.'],
            ['name' => 'gloss-language', 'default' => 'Japanese', 'required' => true,
                'description' => 'The students\' own language, for glosses and translations.'],
            ['name' => 'vocabulary-item', 'required' => true, 'description' => 'The word or phrase.'],
            ['name' => 'level-framework', 'default' => 'CEFR', 'description' => 'The framework of levels, e.g. CEFR or JLPT.'],
            ['name' => 'level', 'default' => 'B1', 'description' => 'The level of the definitions and sample sentences, e.g. A2.'],
            ['name' => 'number-of-senses', 'default' => '3', 'description' => 'The most senses to give.'],
            ['name' => 'number-of-samples', 'default' => '2', 'description' => 'How many sample sentences to write for each sense.'],
        ],
        'formatlinks' => ['*' => 'dictionary-entry-format'],
    ],
    'dictionary-definition' => [
        'type' => 'prompt',
        'name' => 'Dictionary: definition and gloss for one sense',
        'description' => 'A definition at a level, and a gloss, for one sense of a vocabulary item.',
        'extends' => 'dictionary-entry',
        'body' => '{{block:task}}' . <<<'EOD'
Write a definition and a gloss in {{gloss-language}} for one sense of the {{word-language}} vocabulary item "{{vocabulary-item}}": the {{part-of-speech}} in the sense of "{{sense-meaning}}".
Use only {{level-framework}} level {{level}} {{word-language}} in the definition.
EOD . '{{endblock}}' .
            '{{block:description}}{{endblock}}',
        'parameters' => [
            ['name' => 'part-of-speech', 'required' => true, 'description' => 'The part of speech of the sense, e.g. noun or verb.'],
            ['name' => 'sense-meaning', 'required' => true, 'description' => 'A few words that tell the sense apart from others.'],
        ],
        'formatlinks' => ['*' => 'dictionary-definition-format'],
    ],
    'dictionary-samples' => [
        'type' => 'prompt',
        'name' => 'Dictionary: sample sentences for one sense',
        'description' => 'Sample sentences at a level, with translations, for one sense of a vocabulary item.',
        'extends' => 'dictionary-entry',
        'body' => '{{block:task}}' . <<<'EOD'
Create {{number-of-samples}} {{level-framework}} level {{level}} sample sentences in {{word-language}} for the {{part-of-speech}} "{{vocabulary-item}}" in the sense of "{{sense-meaning}}".
Translate each sample sentence into {{gloss-language}}.
EOD . '{{endblock}}' .
            '{{block:description}}{{endblock}}',
        'parameters' => [
            ['name' => 'part-of-speech', 'required' => true, 'description' => 'The part of speech of the sense, e.g. noun or verb.'],
            ['name' => 'sense-meaning', 'required' => true, 'description' => 'A few words that tell the sense apart from others.'],
        ],
        'formatlinks' => ['*' => 'dictionary-samples-format'],
    ],
    'dictionary-related' => [
        'type' => 'prompt',
        'name' => 'Dictionary: related words for one sense',
        'description' => 'Synonyms, antonyms, collocations and the word family, for one sense of a vocabulary item.',
        'extends' => 'dictionary-entry',
        'body' => '{{block:task}}' . <<<'EOD'
List the words and phrases that are related to the {{word-language}} {{part-of-speech}} "{{vocabulary-item}}" in the sense of "{{sense-meaning}}": its synonyms, antonyms, collocations and word family.
Give only words and phrases that a {{level-framework}} level {{level}} student might need.
EOD . '{{endblock}}' .
            '{{block:description}}{{endblock}}',
        'parameters' => [
            ['name' => 'part-of-speech', 'required' => true, 'description' => 'The part of speech of the sense, e.g. noun or verb.'],
            ['name' => 'sense-meaning', 'required' => true, 'description' => 'A few words that tell the sense apart from others.'],
            ['name' => 'number-of-related', 'default' => '3', 'description' => 'The most related items to give for each relation.'],
        ],
        'formatlinks' => ['*' => 'dictionary-related-format'],
    ],
];
