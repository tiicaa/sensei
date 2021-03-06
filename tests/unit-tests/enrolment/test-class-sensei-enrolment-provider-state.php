<?php

/**
 * Tests for Sensei_Enrolment_Provider_State class.
 *
 * @group course-enrolment
 */
class Sensei_Enrolment_Provider_State_Test extends WP_UnitTestCase {
	use Sensei_Course_Enrolment_Test_Helpers;

	/**
	 * Setup function.
	 */
	public function setUp() {
		parent::setUp();

		self::resetEnrolmentStateStores();
	}

	/**
	 * Clean up after all tests.
	 */
	public static function tearDownAfterClass() {
		parent::tearDownAfterClass();
		self::resetEnrolmentStateStores();
	}

	/**
	 * Tests to make sure arrays of serialized data return an instantiated object.
	 */
	public function testFromSerializedArray() {
		$state_store = Sensei_Enrolment_Provider_State_Store::get( 0, 0 );
		$test_array  = [
			'd' => [
				'test' => 'Dinosaurs!',
			],
			'l' => [
				[
					time(),
					'This is a test.',
				],
			],
		];

		$result = Sensei_Enrolment_Provider_State::from_serialized_array( $state_store, $test_array );

		$this->assertTrue( $result instanceof Sensei_Enrolment_Provider_State, 'Serialized data should have returned a instantiated object' );
		$this->assertEquals( $test_array['l'], $result->get_logs() );
		$this->assertEquals( $test_array['d']['test'], $result->get_stored_value( 'test' ) );
	}

	/**
	 * Tests to make sure invalid JSON strings return false.
	 */
	public function testFromSerializedEmptyArrayFails() {
		$state_store = Sensei_Enrolment_Provider_State_Store::get( 0, 0 );
		$result      = Sensei_Enrolment_Provider_State::from_serialized_array( $state_store, [] );

		$this->assertFalse( $result, 'Invalid serialized array should have returned false' );
	}

	/**
	 * Tests to make sure the object is JSON serialized properly.
	 */
	public function testJsonSerializeValid() {
		$state_store = Sensei_Enrolment_Provider_State_Store::get( 0, 0 );
		$test_array  = [
			'd' => [
				'test' => 'Dinosaurs!',
			],
			'l' => [
				[
					time(),
					'This is a test.',
				],
			],
		];

		$state = Sensei_Enrolment_Provider_State::from_serialized_array( $state_store, $test_array );

		$this->assertEquals( \wp_json_encode( $test_array ), \wp_json_encode( $state ) );
	}

	/**
	 * Test to make sure we can get a stored data value that has been set.
	 */
	public function testGetStoredValueThatHasBeenSet() {
		$state_store = Sensei_Enrolment_Provider_State_Store::get( 0, 0 );
		$test_array  = [
			'd' => [
				'test' => 'value',
			],
		];

		$state = Sensei_Enrolment_Provider_State::from_serialized_array( $state_store, $test_array );

		$this->assertEquals( $test_array['d']['test'], $state->get_stored_value( 'test' ) );
	}

	/**
	 * Test to make sure data values that have not been set return as null.
	 */
	public function testGetStoredValueThatHasNotBeenSet() {
		$state_store = Sensei_Enrolment_Provider_State_Store::get( 0, 0 );
		$test_object = [
			'd' => [],
		];
		$test_string = \wp_json_encode( $test_object );
		$state       = Sensei_Enrolment_Provider_State::from_serialized_array( $state_store, $test_string );

		$this->assertEquals( null, $state->get_stored_value( 'test' ) );
	}

	/**
	 * Tests to ensure the enrolment status can be set.
	 */
	public function testSetDataValue() {
		$state_store = Sensei_Enrolment_Provider_State_Store::get( 0, 0 );
		$state       = Sensei_Enrolment_Provider_State::create( $state_store );

		$state->set_stored_value( 'test', true );

		$this->assertTrue( $state->get_stored_value( 'test' ) );

		$json_string = \wp_json_encode( $state );
		$this->assertEquals( '{"d":{"test":true},"l":[]}', $json_string, 'The set data value should persist when serializing the object' );
	}

	/**
	 * Tests to ensure the enrolment status can be cleared.
	 */
	public function testClearStoredValue() {
		$state_store   = Sensei_Enrolment_Provider_State_Store::get( 0, 0 );
		$initial_state = [
			'd' => [],
			'l' => [],
		];

		$state = Sensei_Enrolment_Provider_State::from_serialized_array( $state_store, $initial_state );
		$state->set_stored_value( 'test', true );

		$this->assertTrue( $state->get_stored_value( 'test' ) );

		$state->set_stored_value( 'test', null );
		$this->assertEquals( null, $state->get_stored_value( 'test' ) );

		$json_string     = \wp_json_encode( $state );
		$expected_string = \wp_json_encode( $initial_state );

		$this->assertEquals( $expected_string, $json_string, 'Setting the value to null should persist with data not set when serializing the object' );
	}

	/**
	 * Tests logging a simple message.
	 */
	public function testAddLogMessage() {
		$state_store = Sensei_Enrolment_Provider_State_Store::get( 0, 0 );
		$state       = Sensei_Enrolment_Provider_State::create( $state_store );

		$test_message = 'I really hope this works';
		$state->add_log_message( $test_message );
		$logs = $state->get_logs();

		$this->assertEquals( $logs[0][1], $test_message, 'The message should match what was added' );
	}

	/**
	 * Tests pruning the most recent 30 messages on log add.
	 */
	public function testPruneOnAddLog() {
		$time         = time();
		$initial_data = [
			'l' => [],
		];
		for ( $i = 0; $i < 31; $i++ ) {
			$initial_data['l'][] = [
				$time + $i,
				'Log entry ' . $i,
			];
		}

		$state_store = Sensei_Enrolment_Provider_State_Store::get( 0, 0 );
		$state       = Sensei_Enrolment_Provider_State::from_serialized_array( $state_store, $initial_data );

		$state->add_log_message( 'This should cause a pruning' );
		$logs = $state->get_logs();

		$this->assertEquals( 30, count( $logs ), 'The log should have been pruned to just 30 entries.' );
		$this->assertEquals( $initial_data['l'][2], $logs[0], 'The first log entry should be the original second oldest' );
		$this->assertEquals( 'This should cause a pruning', $logs[29][1], 'The last log entry should be the entry we just added' );
	}

	/**
	 * Tests getting log message with oldest at the top.
	 */
	public function testGetLogs() {
		$state_store = Sensei_Enrolment_Provider_State_Store::get( 0, 0 );
		$state       = Sensei_Enrolment_Provider_State::create( $state_store );

		$initial_data = [
			'First log entry',
			'Second log entry',
			'Third log entry',
			'Fourth log entry',
		];
		foreach ( $initial_data as $entry ) {
			$state->add_log_message( $entry );
		}

		$logs = $state->get_logs();

		$this->assertEquals( $initial_data[0], $logs[0][1], 'The first log entry should be the oldest' );
		$this->assertEquals( $initial_data[1], $logs[1][1], 'The second log entry should be the second oldest' );
		$this->assertEquals( $initial_data[2], $logs[2][1], 'The third log entry should be the third oldest' );
		$this->assertEquals( $initial_data[3], $logs[3][1], 'The last log entry should be the newest' );
	}
}
