<?php


namespace calderawp\calderaforms\Tests\Integration\RestApi;


use calderawp\calderaforms\cf2\Exception;
use calderawp\calderaforms\cf2\RestApi\Process\Submission;
use calderawp\calderaforms\cf2\RestApi\Token\FormJwt;

class SubmitFormTest extends RestApiTestCase
{

	/** @inheritdoc */
	public function setUp()
	{
		parent::setUp();
	}

	public function tearDown()
	{
		parent::tearDown(); // TODO: Change the autogenerated stub
	}

	/**
	 * @covers \calderawp\calderaforms\cf2\RestApi\Submission::getArgs()
	 * @covers \calderawp\calderaforms\cf2\RestApi\Submission::add_routes()
	 * @covers \calderawp\calderaforms\cf2\RestApi\Register::initEndpoints()
	 * @covers \calderawp\calderaforms\cf2\RestApi\Submission::getNamespace()
	 *
	 * @since 1.9.0
	 *
	 * @group cf2
	 */
	public function testRouteCanBeRequest()
	{
		$request = new \WP_REST_Request('GET', '/cf-api/v3');
		$response = rest_get_server()->dispatch($request);
		$endpoint = '/cf-api/v3/' . Submission::URI . '/(?P<formId>[\w-]+)';
		$this->assertTrue(
			array_key_exists($endpoint, $response->get_data()[ 'routes' ])
		);
		$this->assertTrue(
			in_array('POST', $response->get_data()[ 'routes' ][ $endpoint ][ 'methods' ])
		);

	}

	/**
	 * Test we can create entries
	 *
	 * @covers \calderawp\calderaforms\cf2\RestApi\Process\Submission::createItem()
	 *
	 * @since 1.9.0
	 *
	 * @group cf2
	 * @group now
	 */
	public function testCreateItem()
	{
		$formId = $this->importAutoresponderForm();
		$form = \Caldera_Forms_Forms::get_form($formId);
		$this->assertFalse(empty($form[ 'fields' ]));
		$route = new Submission();
		$valueOfTextField = 'Value of text field';
		$entryData = [
			'fld_8768091' => 'Roy',
			'fld_9970286' => 'Sivan',
			'fld_6009157' => 'sivan@hiroy.club',
			'fld_7683514' => 'Hi Roy',
		];
		$request = new \WP_REST_Request();
		$request->set_url_params(['formId' => $formId]);
		$request->set_param(Submission::VERIFY_FIELD, 'sdadgfhjkl;kgfdsa123456ytrfdas');
		$sessionId = '111ads132456432lk';
		$request->set_param(Submission::SESSION_ID_FIELD,$sessionId);
		$request->set_param('entryData', $entryData);

		wp_set_current_user(1);


		$entryObject = new \Caldera_Forms_Entry_Entry();
		$entryObject->status = 'pending';
		$entryObject->form_id = $formId;
		$entryObject->user_id = get_current_user_id();
		$entryObject->datestamp = date_i18n('Y-m-d H:i:s', time(), 0);
		$entry = new \Caldera_Forms_Entry($form, false, $entryObject);

		try {
			$response = $route->createItem($request);
		} catch (\Exception $e) {
			var_dump($e);
			exit;
		}


		$this->assertEquals(201, $response->get_status());
		$responseData = $response->get_data();
		$entryId = $responseData[ 'entryId' ];
		$this->assertTrue(is_numeric($entryId));
		$this->assertNotEquals(0, $entryId);
		$form = \Caldera_Forms_Forms::get_form($formId);
		$savedEntry = new \Caldera_Forms_Entry($form, $entryId);
		$this->assertSame($savedEntry->get_entry_id(), $entryId);
		$i = 0;
		foreach ($entryData as $fieldId => $expectedValue) {
			$field = $savedEntry->get_field($fieldId);
			if (is_null($field)) {
				var_dump($fieldId);
				exit;
			}
			$this->assertEquals($entryData[ $fieldId ], $field->get_value());
			$i++;
		}
		$this->assertEquals(count($entryData), $i);
		$this->assertTrue(is_object($savedEntry->get_field(Submission::SESSION_ID_FIELD)));

	}


	/**
	 * Test that cf1 validation filters are applied
	 *
	 * @covers \calderawp\calderaforms\cf2\RestApi\Process\Submission::addEntryFieldToEntryWithFilter()
	 *
	 * @since 1.9.0
	 *
	 * @group cf2
	 * @group now
	 */
	public function testCreateItemWithFilters()
	{
		$formId = $this->importAutoresponderForm();
		$form = \Caldera_Forms_Forms::get_form($formId);
		$this->assertFalse(empty($form[ 'fields' ]));
		$route = new Submission();
		$valueOfTextField = 'Value of text field';
		$entryData = [
			'fld_8768091' => 'Roy',
			'fld_9970286' => 'Sivan',
			'fld_6009157' => 'sivan@hiroy.club',
			'fld_7683514' => 'Hi Roy',
		];

		add_filter( 'caldera_forms_validate_field_email', function(){
			return 'infinite@hats.com';
		});add_filter( 'caldera_forms_validate_field_fld_9970286', function(){
			return 'National Sandwich';
		});
		$request = new \WP_REST_Request();
		$request->set_url_params(['formId' => $formId]);
		$request->set_param(Submission::VERIFY_FIELD, 'sdadgfhjkl;kgfdsa123456ytrfdas');
		$sessionId = '111ads132456432lk';
		$request->set_param(Submission::SESSION_ID_FIELD,$sessionId);
		$request->set_param('entryData', $entryData);

		wp_set_current_user(1);
		try {
			$response = $route->createItem($request);
		} catch (\Exception $e) {
			var_dump($e);
			exit;
		}


		$this->assertEquals(201, $response->get_status());
		$responseData = $response->get_data();
		$entryId = $responseData[ 'entryId' ];
		$form = \Caldera_Forms_Forms::get_form($formId);
		$savedEntry = new \Caldera_Forms_Entry($form, $entryId);
		$this->assertTrue(is_object($savedEntry->get_field('fld_6009157')));
		$this->assertSame( 'infinite@hats.com',$savedEntry->get_field('fld_6009157')->get_value());
		$this->assertTrue(is_object($savedEntry->get_field('fld_9970286')));
		$this->assertSame( 'National Sandwich',$savedEntry->get_field('fld_9970286')->get_value());

	}

	/**
	 * Test permissions callback when valid
	 *
	 * @covers \calderawp\calderaforms\cf2\RestApi\File\CreateFile::permissionsCallback()
	 *
	 * @since 1.9.0
	 *
	 * @group cf2
	 * @group now
	 */
	public function testPermissionsCallback()
	{
		$formId = $this->importAutoresponderForm();
		$jwt = new FormJwt( 'xljksf', site_url() );
		$request = new \WP_REST_Request();
		$request->set_url_params(['formId' => $formId]);
		$sessionId = uniqid(rand());
		$request->set_param(
			Submission::VERIFY_FIELD,
			$jwt->encode($formId, $sessionId )
		);

		$request->set_param(
			Submission::SESSION_ID_FIELD,
			$sessionId
		);
		$route = new Submission();

		$route->setJwt($jwt);
		$this->assertTrue($route->permissionsCallback($request));


	}

	/**
	 * Test permissions callback when now valid
	 *
	 * @covers \calderawp\calderaforms\cf2\RestApi\File\CreateFile::permissionsCallback()
	 *
	 * @since 1.9.0
	 *
	 * @group cf2
	 * @group now
	 */
	public function testPermissionsCallbackNotValid()
	{
		$formId = $this->importAutoresponderForm();
		$jwt = new FormJwt( 'xljksf', site_url() );

		$request = new \WP_REST_Request();
		$request->set_url_params(['formId' => $formId]);
		$sessionId = uniqid(rand());
		$request->set_param(
			Submission::VERIFY_FIELD,
			'infinite-beings-of-light'
		);

		$request->set_param(
			Submission::SESSION_ID_FIELD,
			$sessionId
		);

		$route = new Submission();
		$route->setJwt($jwt);
		$this->assertFalse($route->permissionsCallback($request));


	}
}
