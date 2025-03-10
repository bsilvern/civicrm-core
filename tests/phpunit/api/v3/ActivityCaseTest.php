<?php

/**
 *  Test Activity.get API with the case_id field
 *
 * @package CiviCRM_APIv3
 * @group headless
 */
class api_v3_ActivityCaseTest extends CiviCaseTestCase {
  /**
   * @var array
   *  APIv3 Result (Case.create)
   */
  protected $_case;

  /**
   * @var array
   *  APIv3 Result (Activity.create)
   */
  protected $_otherActivity;

  /**
   * Test setup for every test.
   *
   * Connect to the database, truncate the tables that will be used
   * and redirect stdin to a temporary file.
   */
  public function setUp(): void {
    parent::setUp();
    $this->_case = $this->createTestEntity('Case', [
      'case_type_id:name' => 'housing_support',
      'subject' => 'new case',
      'contact_id' => $this->individualCreate(),
    ]);

    $this->_otherActivity = $this->createTestEntity('Activity', [
      'source_contact_id' => $this->ids['Contact']['individual_0'],
      'activity_type_id:name' => 'Phone Call',
      'subject' => 'Ask not what your API can do for you, but what you can do for your API.',
    ]);
  }

  /**
   * Test activity creation on case based
   * on id or hash present in case subject.
   */
  public function testActivityCreateOnCase(): void {
    $hash = substr(sha1(CIVICRM_SITE_KEY . $this->_case['id']), 0, 7);
    $subjectArr = [
      "[case #{$this->_case['id']}] test activity recording under case with id",
      "[case #{$hash}] test activity recording under case with id",
    ];
    foreach ($subjectArr as $subject) {
      $activity = $this->callAPISuccess('Activity', 'create', [
        'source_contact_id' => $this->ids['Contact']['individual_0'],
        'activity_type_id' => 'Phone Call',
        'subject' => $subject,
      ]);
      $case = $this->callAPISuccessGetSingle('Activity', ['return' => ['case_id'], 'id' => $activity['id']]);
      //Check if case id is present for the activity.
      $this->assertEquals($this->_case['id'], $case['case_id'][0]);
    }
  }

  /**
   * Same as testActivityCreateOnCase but editing an existing non-case activity
   */
  public function testActivityEditAddingCaseIdInSubject(): void {
    $activity = $this->callAPISuccess('Activity', 'create', [
      'source_contact_id' => $this->ids['Contact']['individual_0'],
      'activity_type_id' => 'Meeting',
      'subject' => 'Starting as non-case activity 1',
    ]);
    $hash = substr(sha1(CIVICRM_SITE_KEY . $this->_case['id']), 0, 7);
    // edit activity and put hash in the subject
    $activity = $this->callAPISuccess('Activity', 'create', [
      'id' => $activity['id'],
      'subject' => "Now should be a case activity 1 [case #{$hash}]",
    ]);
    $case = $this->callAPISuccessGetSingle('Activity', ['return' => ['case_id'], 'id' => $activity['id']]);
    // It should be filed on the case now
    $this->assertEquals($this->_case['id'], $case['case_id'][0]);

    // Now same thing but just with the id not the hash
    $activity = $this->callAPISuccess('Activity', 'create', [
      'source_contact_id' => $this->ids['Contact']['individual_0'],
      'activity_type_id' => 'Meeting',
      'subject' => 'Starting as non-case activity 2',
    ]);
    $activity = $this->callAPISuccess('Activity', 'create', [
      'id' => $activity['id'],
      'subject' => "Now should be a case activity 2 [case #{$this->_case['id']}]",
    ]);
    $case = $this->callAPISuccessGetSingle('Activity', ['return' => ['case_id'], 'id' => $activity['id']]);
    $this->assertEquals($this->_case['id'], $case['case_id'][0]);
  }

  public function testGet(): void {
    $this->assertTrue(is_numeric($this->_case['id']));
    $this->assertTrue(is_numeric($this->_otherActivity['id']));

    $getByCaseId = $this->callAPISuccess('Activity', 'get', [
      'case_id' => $this->_case['id'],
    ]);
    $this->assertNotEmpty($getByCaseId['values']);
    $getByCaseId_ids = array_keys($getByCaseId['values']);

    $getByCaseNotNull = $this->callAPISuccess('Activity', 'get', [
      'case_id' => ['IS NOT NULL' => 1],
    ]);
    $this->assertNotEmpty($getByCaseNotNull['values']);
    $getByCaseNotNull_ids = array_keys($getByCaseNotNull['values']);

    $getByCaseNull = $this->callAPISuccess('Activity', 'get', [
      'case_id' => ['IS NULL' => 1],
    ]);
    $this->assertNotEmpty($getByCaseNull['values']);
    $getByCaseNull_ids = array_keys($getByCaseNull['values']);

    $this->assertTrue(in_array($this->_otherActivity['id'], $getByCaseNull_ids));
    $this->assertNotTrue(in_array($this->_otherActivity['id'], $getByCaseId_ids));
    $this->assertEquals($getByCaseId_ids, $getByCaseNotNull_ids);
    $this->assertEquals([], array_intersect($getByCaseId_ids, $getByCaseNull_ids));
  }

  public function testActivityGetWithCaseInfo(): void {
    $activities = $this->callAPISuccess('Activity', 'get', [
      'sequential' => 1,
      'case_id' => $this->_case['id'],
      'return' => ['case_id', 'case_id.subject'],
    ]);
    $this->assertEquals('new case', $activities['values'][0]['case_id.subject']);
    // Note - case_id is always an array
    $this->assertEquals($this->_case['id'], $activities['values'][0]['case_id'][0]);
  }

}
