@mod @mod_vocab
Feature: Manage the games of a Vocabulary activity on its page
  In order to give students games to practise their vocabulary
  As a teacher
  I need to add, edit, hide, show and delete the games of an activity

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
      | student1 | Sam1      | Student1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity        | vocab   |
      | course          | C1      |
      | idnumber        | vocab1  |
      | name            | Vocab 1 |
      | stage_recognize | 1       |
      | stage_recall    | 1       |

  Scenario: A teacher manages games without JavaScript
    Given I am logged in as "teacher1"
    And I am on the "vocab1" "Activity" page
    Then I should see "This activity has no games yet."
    # Only the quiz game is installed, so "Add a game" goes straight to its settings.
    When I click on "Add a game" "link"
    And I set the following fields to these values:
      | Stage | Recall    |
      | Title | Food quiz |
    And I press "Save changes"
    Then I should see "The game was saved."
    And I should see "Food quiz"
    When I click on "Food quiz" "link"
    And I set the field "Title" to ""
    And I press "Save changes"
    Then I should see "Quiz (Recall)"
    When I click on "Hide game" "link"
    Then I should see "The game is now hidden from students."
    And I should see "Hidden from students"
    When I click on "Show game" "link"
    Then I should see "The game is now shown to students."
    And I should not see "Hidden from students"
    When I click on "Delete game" "link"
    Then I should see "Delete the game \"Quiz (Recall)\" from this activity?"
    When I press "Continue"
    Then I should see "The game was deleted."
    And I should see "This activity has no games yet."

  @javascript
  Scenario: A teacher manages games in popups
    Given I am logged in as "teacher1"
    And I am on the "vocab1" "Activity" page
    When I click on "Add a game" "link"
    And I set the following fields in the "Add a game" "dialogue" to these values:
      | Stage | Recognize |
      | Title | Food quiz |
    And I click on "Save changes" "button" in the "Add a game" "dialogue"
    Then I should see "Food quiz"
    When I click on "Food quiz" "link"
    And I set the field "Title" in the "Edit game" "dialogue" to "Animal quiz"
    And I click on "Save changes" "button" in the "Edit game" "dialogue"
    Then I should see "Animal quiz"
    And I should not see "Food quiz"
    When I click on "Actions" "button" in the "[data-title='Animal quiz']" "css_element"
    And I click on "Hide game" "link" in the "[data-title='Animal quiz']" "css_element"
    Then I should see "Hidden from students"
    When I click on "Actions" "button" in the "[data-title='Animal quiz']" "css_element"
    And I click on "Delete game" "link" in the "[data-title='Animal quiz']" "css_element"
    Then I should see "Delete the game \"Animal quiz\" from this activity?"
    When I click on "Delete" "button" in the "Delete game" "dialogue"
    Then I should see "This activity has no games yet."

  Scenario: Students see only the games that are shown, at stages that are used
    Given I am logged in as "teacher1"
    And I am on the "vocab1" "Activity" page
    And I click on "Add a game" "link"
    And I set the following fields to these values:
      | Stage | Recognize |
      | Title | Shown quiz |
    And I press "Save changes"
    And I click on "Add a game" "link"
    And I set the following fields to these values:
      | Stage | Recall      |
      | Title | Hidden quiz |
    And I press "Save changes"
    And I click on "Hide game" "link" in the "[data-title='Hidden quiz']" "css_element"
    And I log out
    When I am logged in as "student1"
    And I am on the "vocab1" "Activity" page
    Then I should see "Shown quiz"
    And I should not see "Hidden quiz"
    And I should not see "Add a game"

  @javascript @accessibility
  Scenario: A teacher reorders games, and drags a game to the trash
    Given the following "mod_vocab > game instances" exist:
      | vocab  | game | stage     | title       |
      | vocab1 | quiz | recognize | First quiz  |
      | vocab1 | quiz | recognize | Second quiz |
      | vocab1 | quiz | recall    | Third quiz  |
    And I am logged in as "teacher1"
    And I am on the "vocab1" "Activity" page
    And the page should meet accessibility standards
    When I click on "Move Third quiz" "button"
    And I click on "To the top of the list" "link" in the "Move Third quiz" "dialogue"
    And I reload the page
    Then "Third quiz" "text" should appear before "First quiz" "text"
    And "First quiz" "text" should appear before "Second quiz" "text"
    When I drag "Move First quiz" "button" and I drop it in "[data-region='vocab-game-trash']" "css_element"
    Then I should see "Delete the game \"First quiz\" from this activity?"
    When I click on "Delete" "button" in the "Delete game" "dialogue"
    Then I should see "Third quiz"
    And I should see "Second quiz"
    And I should not see "First quiz"
