<?php

namespace luya\headless\tests;

use luya\headless\tests\HeadlessTestCase;
use luya\headless\ActiveEndpoint;

class ActiveEndpointTest extends HeadlessTestCase
{
    public function testNewModel()
    {
        $model = new TestingActiveEndpoint();
        $model->firstname = 'John';
        $model->lastname = 'Doe';
        
        $this->assertTrue($model->getIsNewRecord());
        $response = $model->save($this->createDummyClient('{"firstname":"J", "lastname": "D"}'));
        $this->assertTrue($response);
        $this->assertSame('John', $model->firstname); // response does not modify the current model value.
        $this->assertTrue($model->getIsNewRecord()); // its also a new model after saving
    }
    
    public function testNewModelWithError()
    {
        $model = new TestingActiveEndpoint();
        $model->firstname = 'John';
        $model->lastname = 'Doe';
        
        $this->assertTrue($model->getIsNewRecord());
        $response = $model->save($this->createDummyClient('[{"field":"firstname", "message": "This name is just hilarious and therefore invalid!"}]', false, 301));
        $this->assertFalse($response);
        $this->assertSame([
            [
            'field' => 'firstname',
            'message' => 'This name is just hilarious and therefore invalid!',
            ]
        ], $model->getErrors()); // response does not modify the current model value.
            
        $this->assertTrue($model->hasError());
        $this->assertTrue($model->getIsNewRecord()); // its also a new model after saving

        $this->assertSame(['firstname' => ['This name is just hilarious and therefore invalid!']], $model->getAttributeErrors());
        $this->assertSame(['This name is just hilarious and therefore invalid!'], $model->getAttributeErrors('firstname'));
    }
    
    public function testGetPrimaryKeyValue()
    {
        $model = new TestingActiveEndpoint();
        $model->firstname = 'John';
        $model->lastname = 'Doe';
        
        $this->assertSame('John,Doe', $model->getPrimaryKeyValue());
    }
    
    public function testGetAttributes()
    {
        $model = new TestingActiveEndpoint();
        $this->assertSame(['firstname', 'lastname'], $model->attributes());
    }

    public function testToArray()
    {
        $model = new TestingActiveEndpoint();
        $model->firstname = 'foo';
        $model->lastname = 'bar';

        $this->assertSame(['firstname' => 'foo', 'lastname' => 'bar'], $model->toArray());
        $this->assertSame(['firstname' => 'foo'], $model->toArray(['firstname']));
    }
    
    public function testUpdateExistingModel()
    {
        $model = TestingActiveEndpoint::viewOne(1, $this->createDummyClient('{"firstname":"Baz", "lastname": "Qux"}'));
        
        $this->assertSame('Baz', $model->firstname);
        $this->assertSame('Qux', $model->lastname);
        
        $this->assertFalse($model->getIsNewRecord());
        
        $model->firstname = 'John';
        $model->lastname = 'Doe';
        
        $this->assertTrue($model->save($this->createDummyClient('{}')));
    }
    
    public function testAllWithError()
    {
        $this->expectException('luya\headless\exceptions\RequestException');
        $model = TestingActiveEndpoint::find()->all($this->createDummyClient('[]', false));
    }
    
    public function testViewOneWithError()
    {
        $this->assertFalse(TestingActiveEndpoint::viewOne(1, $this->createDummyClient('[]', false)));
    }
    
    public function testFindAllIterator()
    {
        $model = TestingActiveEndpoint::find()->all($this->createDummyClient('[{"firstname":"John", "lastname": "Doe"}]'));
        
        $this->assertSame(1, count($model->getModels()));
        
        foreach ($model->getModels() as $k => $v) {
            $this->assertSame('John,Doe', $k); // composite primary key test
        }
    }
    
    public function testFindAllPagesIterator()
    {
        $models = TestingActiveEndpoint::findAllPages($this->createDummyClient('[{"firstname":"John", "lastname": "Doe"}]', true, 200, ['X-Pagination-Current-Page' => 1, 'X-Pagination-Page-Count' => 3]));
        
        foreach ($models as $k => $v) {
            $this->assertSame('John,Doe', $k); // composite primary key test
        }
    }
    
    public function testInsert()
    {
        $test = new TestingActiveEndpoint();
        $response = $test->insert(['foo' => 'bar'])->response($this->createDummyClient('{"foo":"bar"}'));
        $this->assertTrue($response->isSuccess());
    }
    
    public function testUpdate()
    {
        $test = new TestingActiveEndpoint();
        $request = $test->update(1, ['bar' => 'foo']);
        $response = $request->response($this->createDummyClient('{"bar":"foo"}'));
        $this->assertTrue($response->isSuccess());
        $this->assertSame(['bar' => 'foo'], $request->getArgs());
        $this->assertSame('{{%testing-active-endpoint}}/1', $request->getEndpoint());
    }
    
    public function testView()
    {
        $test = new TestingActiveEndpoint();
        $request = $test->view(1);
        $response = $request->response($this->createDummyClient('{"bar":"foo"}'));
        $this->assertTrue($response->isSuccess());
        $this->assertSame('{{%testing-active-endpoint}}/1', $request->getEndpoint());
    }
    
    public function testRemove()
    {
        $test = new TestingActiveEndpoint();
        $request = $test->remove(1);
        $response = $request->response($this->createDummyClient('{"bar":"foo"}'));
        $this->assertTrue($response->isSuccess());
        $this->assertSame('{{%testing-active-endpoint}}/1', $request->getEndpoint());
    }

    public function testProccessContent()
    {
        $test = new TestingActiveEndpointProcess();
        $response = $test->findAll($this->createDummyClient('{"items": [{"firstname":"John", "lastname":"Doe"}]}'));

        foreach ($response->getModels() as $k => $v) {
            $this->assertSame('John', $v->firstname);
        }
    }

    public function testIterator()
    {
        $iteratorModel = TestingActiveEndpoint::iterator([
            ['firstname' => 'foo', 'lastname' => 'bar'],
        ]);

        $this->assertInstanceOf('luya\headless\base\BaseIterator', $iteratorModel);

        $this->assertSame(1, count($iteratorModel));
        $this->assertSame(['firstname' => 'foo', 'lastname' => 'bar'], $iteratorModel['foo,bar']->toArray());
    }

    public function testLateStateBindingObject()
    {
        $providerOne = TestingActiveEndpoint::find()->all($this->createDummyClient('[{"firstname": "foo", "lastname": "bar"}]', true, 200, ['X-Pagination-Page-Count' => 1]));

        $this->assertSame(1, $providerOne->getPageCount());
        $providerTwo = TestingActiveEndpoint::find()->all($this->createDummyClient('[{"firstname": "foo", "lastname": "bar"}]', true, 200, ['X-Pagination-Page-Count' => 400]));
        $this->assertSame(400, $providerTwo->getPageCount());
        $this->assertSame(1, $providerOne->getPageCount());
    }
}

class TestingActiveEndpoint extends ActiveEndpoint
{
    public $firstname;
    public $lastname;
    
    public static function getPrimaryKeys()
    {
        return ['firstname', 'lastname'];
    }
}

class TestingActiveEndpointProcess extends ActiveEndpoint
{
    public $firstname;
    public $lastname;
    
    public static function getPrimaryKeys()
    {
        return ['firstname', 'lastname'];
    }

    public static function find()
    {
        return parent::find()->setContentProcessor(function($content) {
            return $content['items'];
        });
    }
}