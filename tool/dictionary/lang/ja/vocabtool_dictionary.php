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
 * tool/dictionary/lang/en/vocabtool_dictionary.php
 *
 * @package    vocabtool_dictionary
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['added'] = '追加';
$string['addedcounts'] = '語義 {$a->senses}、定義 {$a->definitions}、訳語 {$a->glosses}、例文 {$a->samples}';
$string['aibridgedisabled'] = 'AIブリッジ（tool_aibridge）が無効なため、AIで辞書を埋めることができません。';
$string['aipolicylink'] = 'AIポリシー';
$string['aipolicynotice'] = 'AIで辞書を埋める前に、{$a}を読んで同意してください。';
$string['aisettings'] = 'AI設定';
$string['approve'] = '承認';
$string['definitions'] = '定義';
$string['dictionary'] = '辞書を編集';
$string['dictionary:approve'] = 'サイト全体の辞書の項目を承認し、承認済みの項目を変更する';
$string['dictionary:edit'] = 'サイト全体の辞書に項目を追加し、下書きと却下された項目を変更する';
$string['draftcountn'] = '下書き: {$a}';
$string['entries'] = '辞書の見出し';
$string['entrysettings'] = '辞書の項目';
$string['error_activitynotfound'] = '語彙活動（id={$a}）が見つかりませんでした。';
$string['error_itemnotfound'] = '辞書の項目（{$a}）は、この活動の単語リストにありません。';
$string['error_promptnotfound'] = '辞書のプロンプト（id={$a}）が見つからないか、使用できません。';
$string['error_tryagain'] = 'AIが今は応答できないため、後で再試行します：{$a}';
$string['error_wordnotfound'] = '単語（id={$a}）が見つかりませんでした。';
$string['fillin'] = '辞書を埋める';
$string['fillingin'] = '「{$a}」の辞書を埋めています';
$string['filltask'] = 'AIで辞書を埋める';
$string['glosses'] = '訳語';
$string['hideall'] = 'すべて隠す';
$string['inprogress'] = '処理中';
$string['lemmais'] = '辞書形: {$a}';
$string['level'] = 'レベル';
$string['level_help'] = '定義と例文のレベルです。AIはこのレベルの語彙と文法だけを使い、各語義のレベルを推定します。';
$string['messages'] = 'メッセージ';
$string['noentry'] = 'この単語には、まだ辞書の見出しがありません。';
$string['nopromptsfound'] = '辞書のプロンプトが見つかりませんでした。<a href="{$a}">AIブリッジのライブラリ</a>を確認してください。';
$string['nowordsfound'] = 'この活動の単語リストにはまだ単語がありません。';
$string['nowordsselected'] = '単語を1つ以上選んでください。';
$string['pluginname'] = '語彙活動で使用される辞書データの編集';
$string['privacy:metadata:log'] = '教師がAIで辞書を埋めるために設定したタスク。';
$string['privacy:metadata:log:error'] = 'タスクのエラー。';
$string['privacy:metadata:log:prompt'] = 'AIに送信したプロンプト。';
$string['privacy:metadata:log:results'] = 'AIからの応答。';
$string['privacy:metadata:log:timecreated'] = 'タスクを設定した日時。';
$string['privacy:metadata:log:userid'] = 'タスクを設定した教師（AIはこの教師として使用されます）。';
$string['privacy:metadata:log:wordid'] = '辞書の項目を埋める単語。';
$string['prompt'] = 'プロンプト';
$string['reject'] = '却下';
$string['restore'] = '下書きに戻す';
$string['samplecount'] = '各語義の例文の数';
$string['samples'] = '例文';
$string['selectedwords'] = '単語';
$string['selectedwords_help'] = '埋める単語です。辞書にまだ語義がない単語にはチェックが入っています。辞書はサイト全体で共有されるため、他の活動からすでに語義がある単語もあります。';
$string['sensecount'] = '語義の最大数';
$string['sensecount_help'] = '求める語義（意味）の最大数です。AIはよく使われる語義だけを返すため、多くの単語ではこれより少なくなります。';
$string['sensecountn'] = '語義: {$a}';
$string['showall'] = 'すべて表示';
$string['skipexisting'] = 'すでに語義がある単語をスキップ';
$string['skipexisting_help'] = 'チェックすると、辞書にすでに語義がある単語はAIに再送信されません。別のレベルなどで語義・定義・例文を追加するには、チェックを外してください。';
$string['status'] = '状態';
$string['statuscompleted'] = '完了';
$string['statusdelayed'] = '後で再試行';
$string['statusfailed'] = '失敗';
$string['statusfetching'] = 'AIの応答を待機中';
$string['statusnotset'] = '未設定';
$string['statusqueued'] = '待機中';
$string['statussaving'] = '保存中';
$string['statusskipped'] = 'スキップ（すでに語義あり）';
$string['tasks'] = 'タスク';
$string['tasksqueued'] = '{$a} 件のタスクを登録しました。バックグラウンドで実行され、進行状況は下に表示されます。';
$string['textassistant'] = 'AIプロバイダ';
$string['word'] = '単語';
$string['wordlist'] = '単語リスト';
$string['wordpending'] = '{$a}（処理中）';
$string['wordsenses'] = '{$a->word}（語義 {$a->count}）';
