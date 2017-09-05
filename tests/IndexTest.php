<?php

namespace tests;

class IndexTest extends \PHPUnit_Framework_TestCase
{
    public function testa()
    {
        $ogj = new \app\index\controller\Upload();
//        $ogj->a(10);
        $this->AssertNull($ogj->a(1));
    }

    public function testb()
    {
        $ogj = new \app\index\controller\Upload();
        $this->assertEquals(22, $ogj->a(222));
    }
}