@mod @mod_vocab @vocabtool @vocabtool_dictionary
Feature: See and review the dictionary entries of the words in a Vocabulary activity
  In order to check the dictionary entries that AI has made
  As a teacher
  I need to see each word's entry, and reject or approve its items

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
      | manager1 | Mandy1    | Manager1 | manager1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | manager1 | C1     | manager        |
    And the following "activity" exists:
      | activity | vocab   |
      | course   | C1      |
      | idnumber | vocab1  |
      | name     | Vocab 1 |
    And the following "mod_vocab > words" exist:
      | vocab  | word   |
      | vocab1 | apples |
      | vocab1 | pear   |
    And the following "mod_vocab > dictionary senses" exist:
      | word   | lemma | meaning       | definition                            | gloss | sample                    | form  | translation        |
      | apples | apple | a round fruit | A round fruit with red or green skin. | りんご   | I eat an apple every day. | apple | 私は毎日りんごを食べます。 |

  Scenario: A teacher sees a word's entry without JavaScript
    Given I am logged in as "teacher1"
    And I am on the "vocab1" "vocabtool_dictionary > Dictionary" page
    Then I should see "Senses: 1" in the "apples" "list_item"
    And I should see "Drafts: 5" in the "apples" "list_item"
    And I should not see "A round fruit with red or green skin."
    When I click on "apples" "link"
    Then I should see "Dictionary form: apple"
    And I should see "A round fruit with red or green skin."
    And I should see "りんご"
    And I should see "私は毎日りんごを食べます。"

  @javascript
  Scenario: A teacher rejects and restores an item, but cannot approve it
    Given I am logged in as "teacher1"
    And I am on the "vocab1" "vocabtool_dictionary > Dictionary" page
    When I click on "apples" "link"
    Then I should see "A round fruit with red or green skin."
    And "Approve" "button" should not exist in the ".vocabtool_dictionary_entries" "css_element"
    When I click on "Reject" "button" in the "A round fruit with red or green skin." "list_item"
    Then I should see "Rejected" in the "A round fruit with red or green skin." "list_item"
    When I click on "Restore as draft" "button" in the "A round fruit with red or green skin." "list_item"
    Then I should see "Draft" in the "A round fruit with red or green skin." "list_item"
    And "Restore as draft" "button" should not exist in the "A round fruit with red or green skin." "list_item"
    When I click on "Hide all" "button"
    Then I should not see "A round fruit with red or green skin."
    When I click on "Show all" "button"
    Then I should see "A round fruit with red or green skin."
    And I should see "This word has no dictionary entry yet."

  @javascript
  Scenario: A manager approves an item, and then a teacher cannot change it
    Given I am logged in as "manager1"
    And I am on the "vocab1" "vocabtool_dictionary > Dictionary" page
    When I click on "apples" "link"
    And I click on "Approve" "button" in the "I eat an apple every day." "list_item"
    Then I should see "Approved" in the "I eat an apple every day." "list_item"
    And I log out
    And I am logged in as "teacher1"
    And I am on the "vocab1" "vocabtool_dictionary > Dictionary" page
    And I should see "Drafts: 4" in the "apples" "list_item"
    When I click on "apples" "link"
    Then "Reject" "button" should not exist in the ".vocabtool_dictionary_entries li.mb-1 > .vocabtool_dictionary_item" "css_element"
