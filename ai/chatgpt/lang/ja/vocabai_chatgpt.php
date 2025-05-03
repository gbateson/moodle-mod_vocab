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
 * tool/import/lang/en/vocabai_chatgpt.php
 *
 * @package    vocabai_chatgpt
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['cannoteditkeys'] = 'これらのキーは編集できません。';
$string['chatgpt'] = 'OpenAI ChatGPT';
$string['chatgptkey'] = 'ChatGPTキー';
$string['chatgptkey_help'] = 'OpenAI ChatGPTのAPIにアクセスするために必要なキーです。通常は "sk-" で始まり、続けて48文字のランダムな英数字が続きます。';
$string['chatgptmodel'] = 'ChatGPTモデル';
$string['chatgptmodel_help'] = '使用するOpenAI ChatGPTモデルを選択します（例：gpt-3.5-turbo、gpt-4）。';
$string['chatgptmodelid'] = 'チューニング済ChatGPTモデル';
$string['chatgptmodelid_help'] = 'このチューニングファイルで調整されたOpenAI ChatGPTモデルです。';
$string['chatgpturl'] = 'ChatGPT URL';
$string['chatgpturl_help'] = 'OpenAI ChatGPTのAPIのURLを指定します（例：https://api.openai.com/v1/completions）。';
$string['confirmcopykey'] = 'このキーをコピーしてもよろしいですか？';
$string['confirmdeletekey'] = 'このキーを削除してもよろしいですか？';
$string['copycancelled'] = 'キーのコピーはキャンセルされました。';
$string['copycompleted'] = 'キーのコピーが正常に完了しました。';
$string['copykey'] = 'ChatGPTのAPIキーをコピー';
$string['deletecancelled'] = 'キーの削除はキャンセルされました。';
$string['deletecompleted'] = 'キーの削除が正常に完了しました。';
$string['deletekey'] = 'ChatGPTのAPIキーを削除';
$string['editcancelled'] = 'キーの編集はキャンセルされました。';
$string['editcompleted'] = '変更されたキーが正常に保存されました。';
$string['gpt-3.5-turbo'] = 'シンプルなタスク向けの高速かつ低コストなモデル。';
$string['gpt-4'] = '多言語対応で、複雑な推論が得意なモデル。';
$string['gpt-4-turbo'] = 'GPT-4に似ており、画像の理解も可能。';
$string['gpt-4o'] = '複雑で多段階なタスクに対応する高性能フラッグシップモデル。';
$string['gpt-4o-mini'] = '高速かつ軽量なタスク向けのインテリジェントな小型モデル。';
$string['nokeysfound'] = 'キーが見つかりませんでした。';
$string['note'] = '注';
$string['pluginname'] = '語彙活動用のOpenAI ChatGPT AIアシスタント';
$string['privacy:metadata'] = 'vocabai_chatgptプラグインは、個人データを保存しません。';
$string['temperature'] = '温度';
$string['temperature_help'] = 'この設定は、AIエンジンが次の語をどの程度ランダムに選択するかを制御します。低い値（例：0.2）は、最も可能性の高い語の中からのみ選ばれます。高い値（例：0.7）は、より多様で創造的な出力につながる、可能性の低い語も選択される可能性があります。';
$string['top_p'] = 'Top-P';
$string['top_p_help'] = 'この値は、AIエンジンが次の語を生成する際に使用する語彙の候補プールのサイズを制限します。低い値（例：0.1）は、最も可能性の高い語のみを考慮します。高い値（例：0.7）は、候補語の数が増えます。';
