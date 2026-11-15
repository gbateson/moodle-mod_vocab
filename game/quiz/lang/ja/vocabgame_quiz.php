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
 * Strings for the quiz game of the Vocabulary activity.
 *
 * @package    vocabgame_quiz
 * @copyright  2026 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['anycategory'] = 'このコースのすべてのカテゴリ';
$string['category'] = 'クイズ: 問題カテゴリ';
$string['category_help'] = 'クイズは、このカテゴリとそのサブカテゴリにある単語についての問題を使います。問題バンクツールで作った問題は、その単語に関連付けられます。';
$string['finish'] = '終了';
$string['materialcount'] = 'クイズ: 各単語について作る問題数';
$string['materiallevel'] = 'クイズ: 作る問題のレベル';
$string['materialprompt'] = 'クイズ: 問題を作るためのプロンプト';
$string['materialprompt_help'] = '単語に問題がない場合、教師は問題バンクツールを使ってAIでバックグラウンドで問題を作ることができます。これらの設定は、その方法（AIプロンプトライブラリのプロンプト、問題タイプ、レベル、各単語について作る問題数）を指定します。問題はクイズの問題カテゴリに入れられます。';
$string['materialqtype'] = 'クイズ: 作る問題のタイプ';
$string['noquestionsforwords'] = 'あなたの単語についての問題はまだありません。';
$string['pluginname'] = 'クイズ';
$string['pluginname_help'] = '問題バンクにある単語についての問題（例：問題バンクツールで作った問題）のクイズです。各問題は1つの単語についての問題で、その問題の得点が単語の得点になります。';
$string['privacy:metadata'] = 'クイズゲーム自体は個人データを保存しません。受験と得点は語彙アクティビティが保存します。';
$string['results'] = 'あなたの結果';
$string['resultsscore'] = '得点';
$string['resultsword'] = '単語';
$string['returntoactivity'] = 'アクティビティに戻る';
$string['saveandleave'] = '保存して後で続ける';
$string['wordcount'] = 'クイズ: 1回の問題数';
$string['wordcount_help'] = 'クイズの1回の受験での問題数、つまり単語の数です。';
