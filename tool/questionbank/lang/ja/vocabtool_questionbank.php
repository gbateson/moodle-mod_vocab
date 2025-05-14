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
 * tool/import/lang/en/vocabtool_import.php
 *
 * @package    vocabtool_questionbank
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['activityname'] = '活動名';
$string['addaidetails'] = '「AIアシスタント」メニューのリンクを使用して、必要な詳細を追加してください。';
$string['addname'] = '名前を追加';
$string['addtags'] = 'タグを追加';
$string['adhoctaskid'] = 'アドホックタスク';
$string['adhoctaskid_help'] = '質問生成に使用されるMoodleのアドホックタスクのIDです。';
$string['aisettings'] = 'AI設定';
$string['categorysettings'] = '質問カテゴリ設定';
$string['catinfo_activityname'] = '活動 "{$a->activityname}" の語彙問題';
$string['catinfo_coursename'] = 'コース "{$a->coursename}" の語彙問題';
$string['catinfo_customname'] = '語彙問題';
$string['catinfo_prompthead'] = '{$a->prompthead}（{$a->qtype}）の{$a->qlevel}レベルの問題（単語: "{$a->word}"）';
$string['catinfo_prompttail'] = '{$a->prompttail}（{$a->qtype}）の{$a->qlevel}レベルの問題（単語: "{$a->word}"）';
$string['catinfo_questiontype'] = '{$a->word} に関する {$a->qtype} 問題';
$string['catinfo_sectionname'] = 'コース "{$a->coursename}" の {$a->sectiontype} "{$a->sectionname}" にある語彙問題';
$string['catinfo_vocablevel'] = '{$a->word} の {$a->qtype} 問題（{$a->qlevel}）';
$string['catinfo_word'] = '単語 "{$a->word}" の語彙問題';
$string['catname_activityname'] = '{$a->activitytype}: {$a->activityname}';
$string['catname_coursename'] = 'コース: {$a->coursename}';
$string['catname_customname'] = '{$a->customname}';
$string['catname_prompthead'] = '単語: {$a->word}（{$a->qtype}）{$a->qlevel}（{$a->prompthead}）';
$string['catname_prompttail'] = '単語: {$a->word}（{$a->qtype}）{$a->qlevel}（{$a->prompttail}）';
$string['catname_questiontype'] = '単語: {$a->word}（{$a->qtype}）';
$string['catname_sectionname'] = '{$a->sectiontype}: {$a->sectionname}';
$string['catname_vocablevel'] = '単語: {$a->word}（{$a->qtype}）{$a->qlevel}';
$string['catname_word'] = '単語: {$a->word}';
$string['cefr_a1_description'] = 'A1：基礎レベル';
$string['cefr_a2_description'] = 'A2：初級レベル';
$string['cefr_b1_description'] = 'B1：中級レベル';
$string['cefr_b2_description'] = 'B2：中上級レベル';
$string['cefr_c1_description'] = 'C1：上級レベル';
$string['cefr_c2_description'] = 'C2：熟達レベル';
$string['checkingparams'] = '... パラメータを確認中 ...';
$string['ddimageortextshort'] = 'DD（画像/テキスト）';
$string['defaultcustomname'] = '{$a} のための問題';
$string['deletelog'] = 'ログを削除';
$string['deletelogresult'] = '{$a->count} 件のログレコードを削除しました {$a->ids}';
$string['deletelogresults'] = '{$a->count} 件のログレコードを削除しました {$a->ids}';
$string['descriptionshort'] = '説明';
$string['editlog'] = 'ログを編集';
$string['editlogresult'] = '{$a->count} 件のログを更新しました {$a->ids}';
$string['editlogsresult'] = '{$a->count} 件のログを更新しました {$a->ids}';
$string['emptyparentcategoryelements'] = '質問カテゴリを選択してください。';
$string['emptyquestioncount'] = '質問数は1以上でなければなりません。';
$string['emptyquestionlevels'] = '少なくとも1つのレベルを選択してください。';
$string['emptyquestiontypes'] = '少なくとも1つの問題タイプを選択してください。';
$string['emptyresults'] = 'AIアシスタントからの結果が空でした。';
$string['emptyselectedwords'] = '少なくとも1つの単語を選択してください。';
$string['emptysubcategorieselements'] = '少なくとも1つの質問サブカテゴリタイプを選択してください。';
$string['error_emptyresults'] = 'AIアシスタントからの結果が空または存在しません。質問を生成またはインポートできませんでした。';
$string['error_failedtoconnect'] = '{$a->ai} {$a->configid} への接続に失敗しました';
$string['error_generatequestions'] = '質問を生成できませんでした：{$a}。';
$string['error_invalidfile'] = '無効なAIファイル（ID={$a->id}）：{$a->name}';
$string['error_invalidformat'] = '無効なAI形式（ID={$a->id}）：{$a->name}';
$string['error_invalidlogid'] = 'アドホックタスクから受け取った無効なログ（{$a}）';
$string['error_invalidprompt'] = '無効なAIプロンプト（ID={$a->id}）：{$a->name}';
$string['error_invalidquestioncategoryid'] = '質問生成タスクに送信された無効なカテゴリID（{$a}）';
$string['error_invalidtaskparameters'] = 'アドホックタスクログの無効なパラメータ：{$a}';
$string['error_invalidteacherid'] = 'ユーザー（ID={$a->userid}）は、コース（ID={$a->courseid}）で質問を作成する権限がありません。';
$string['error_invaliduserid'] = '質問生成タスクに送信された無効なユーザーID（{$a}）';
$string['error_invalidvocabid'] = '質問生成タスクに送信された無効な語彙ID（{$a}）';
$string['error_invalidwordid'] = '質問生成タスクに送信された無効な単語ID（{$a}）';
$string['error_missingcoursecategory'] = '単語 "{$a}" の質問カテゴリが見つからず、作成もできませんでした。';
$string['error_missingwordinstance'] = '単語 "{$a->word}" はこの語彙活動の単語リストに存在しません。';
$string['error_recordnotadded'] = 'テーブル {$a->table} にレコードを追加できませんでした：{$a->record}';
$string['essayautogradeshort'] = 'エッセイ（自動）';
$string['female'] = '女性';
$string['filedescription'] = 'AIチューニングファイル';
$string['fixquestion'] = '質問を修正';
$string['fixquestions'] = '質問を修正';
$string['fixquestionsresult'] = '{$a->count} 件のログレコードを修正しました {$a->ids}';
$string['fixquestionsresults'] = '{$a->count} 件のログレコードを修正しました {$a->ids}';
$string['formatname'] = 'AI形式名';
$string['gapselectshort'] = 'ギャップ';
$string['generatequestions'] = '質問を生成';
$string['generatingquestions'] = '... 単語 "{$a->word}" に対して {$a->count} 件の質問を生成中 ...';
$string['importingquestions'] = '... 単語 "{$a->word}" に対する {$a->count} 件の質問をインポート中 ...';
$string['invalidquestioncategory'] = '無効な質問カテゴリ（ID={$a}）';
$string['logrecords'] = 'ログ記録 {$a}';
$string['male'] = '男性';
$string['man'] = '男性';
$string['matchshort'] = 'マッチ';
$string['maxtries'] = '最大試行回数';
$string['maxtries_help'] = 'AIによる質問生成を試みる最大回数。通常は1回で十分です。';
$string['missingaidetails'] = '以下の設定が定義されていないため、質問をまだ生成できません：{$a}';
$string['missingconfigname'] = '設定が見つかりません（ID={$a->configid}, 種類={$a->type}）';
$string['moodlequestions'] = 'Moodle 質問';
$string['multianswershort'] = '穴埋め';
$string['multichoiceshort'] = '選択肢';
$string['noassistantsfound'] = '{$a} AIアシスタントへのアクセス情報がありません';
$string['noaudiosfound'] = '音声アシスタントが見つかりませんでした。これは必須ではありませんが、質問に音声を追加したい場合には必要です。<br>{$a}';
$string['nofilesfound'] = 'チューニングファイルが見つかりませんでした。これは必須ではありませんが、AIテキストアシスタントによって生成される質問の質を向上させることができます。<br>{$a}';
$string['noformatsfound'] = '{$a} プロンプトの出力形式が見つかりません';
$string['noimagesfound'] = '画像アシスタントが見つかりませんでした。これは必須ではありませんが、質問に画像を追加したい場合には必要です。<br>{$a}';
$string['nopromptsfound'] = '{$a} AIアシスタント用のプロンプトが見つかりません';
$string['novideosfound'] = '動画アシスタントが見つかりませんでした。これは必須ではありませんが、質問に動画を追加したい場合には必要です。<br>{$a}';
$string['nowordsfound'] = '問題集に質問を生成するには、まずこの語彙活動に単語リストを定義する必要があります。「単語リストの編集」ツールを使用してください。';
$string['orderingshort'] = '並べ替え';
$string['pluginname'] = '語彙活動のための質問生成';
$string['privacy:metadata'] = 'vocabtool_questionbank プラグインは個人データを保存しません。';
$string['prompt'] = 'AIプロンプト';
$string['prompt_help'] = '選択したAIアシスタントに送信するAIプロンプトを選択してください。';
$string['promptname'] = 'AIプロンプト名';
$string['prompttext'] = 'プロンプト本文';
$string['prompttext_help'] = '質問を生成するために使用されるAIプロンプトです。';
$string['questionbank'] = '問題バンク';
$string['questioncategory'] = '質問カテゴリ';
$string['questioncategory_help'] = '新しい質問を追加するカテゴリです。';
$string['questionlevel'] = '質問レベル';
$string['questionlevel_help'] = 'AIが生成する質問に使用する語彙レベルです。';
$string['questionlevels'] = '言語レベル';
$string['questionlevels_help'] = '質問に使用される語彙や文法のレベルです。';
$string['questionsettings'] = '質問設定';
$string['questiontypes'] = '質問タイプ';
$string['questiontypes_help'] = '生成する質問の種類です。';
$string['redotask'] = 'タスクを再実行';
$string['redotaskresult'] = '{$a->count} 件のタスクを再実行します {$a->ids}';
$string['redotaskresults'] = '{$a->count} 件のタスクを再実行します {$a->ids}';
$string['resultsnotparsed'] = '{$a} の結果を解析できませんでした。';
$string['resultstext'] = '結果テキスト';
$string['resultstext_help'] = 'AIアシスタントから受け取った生の結果データです。';
$string['resumetask'] = 'タスクを再開';
$string['resumetaskresult'] = '{$a->count} 件のタスクを再開します {$a->ids}';
$string['resumetaskresults'] = '{$a->count} 件のタスクを再開します {$a->ids}';
$string['sassessmentshort'] = '話す（評価）';
$string['scheduletasksfailure'] = '以下のタスクはスケジュールできませんでした。後でもう一度お試しください。{$a}';
$string['scheduletaskssuccess'] = '以下のタスクは正常にスケジュールされ、Moodle cron によって後で実行されます。{$a}';
$string['sectiontype'] = 'セクションタイプ';
$string['selectedlogrecord'] = '選択されたログレコード';
$string['selectedwords'] = '選択された単語';
$string['selectedwords_help'] = '質問を生成したい単語を選択してください。';
$string['shortanswershort'] = '短答';
$string['speakautogradeshort'] = '話す（自動）';
$string['subcatname'] = 'サブカテゴリ名';
$string['subcatname_help'] = 'カスタムカテゴリ名を指定してください。';
$string['subcattype'] = 'サブカテゴリタイプ';
$string['subcattype_help'] = '親カテゴリ内でのサブカテゴリの階層構造を定義します。';
$string['taskerror'] = 'タスクエラー';
$string['taskerror_help'] = 'Moodleアドホックタスクから報告されたエラーメッセージ（あれば）';
$string['taskexecutor'] = 'タスク実行方式';
$string['taskexecutor_help'] = 'タスクを実行する方法を選択してください。';
$string['taskgeneratequestions'] = '単語 "{$a->word}" に対して、レベル "{$a->qlevel}" の {$a->qtype} 質問を {$a->qcount} 件生成するタスク';
$string['taskowner'] = 'タスクの所有者';
$string['taskowner_help'] = '質問生成に使用されるMoodleアドホックタスクの所有者です。';
$string['taskstatus'] = 'タスクステータス';
$string['taskstatus_addingmultimedia'] = '画像・音声・動画の追加中';
$string['taskstatus_awaitingimport'] = 'インポート待機中';
$string['taskstatus_awaitingreview'] = 'レビュー待ち';
$string['taskstatus_cancelled'] = 'ユーザーによってキャンセルされました';
$string['taskstatus_checkingparams'] = 'パラメータを確認中';
$string['taskstatus_completed'] = 'タスクが正常に完了しました';
$string['taskstatus_failed'] = 'エラーでタスクが失敗しました';
$string['taskstatus_fetchingresults'] = '結果を取得中';
$string['taskstatus_help'] = '質問生成タスクの現在のステータスです。';
$string['taskstatus_importingresults'] = '結果をインポート中';
$string['taskstatus_notset'] = '未設定';
$string['taskstatus_queued'] = 'キューに追加されました';
$string['timecreated'] = '作成日時';
$string['timemodified'] = '更新日時';
$string['tries'] = '試行回数';
$string['tries_help'] = 'AIアシスタントに接続して質問を生成しようとした試行回数。';
$string['truefalseshort'] = '正誤';
$string['withselected'] = '選択済みの項目に対して';
$string['woman'] = '女性';
$string['word_help'] = 'この質問が生成された対象の単語または語彙アイテムです。';
