<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Speed PHP Framework.
# Copyright (C) 2010, 2011 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License.
#
# Photon is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */


namespace photon\tests\mongrel2\mongrel2Test;

use \photon\config\Container as Conf;
use \photon\mongrel2;

class DummyZMQSocket
{
    public $payload = false;
    public $maxsend = null;

    public function setNextRecv($payload)
    {
        $this->payload = $payload;
    }

    public function recv()
    {
        $payload = $this->payload;
        $this->payload = false;
        return $payload;
    }

    public function send($payload)
    {
        if ($this->maxsend === null) {
            return true;
        }
        if ($this->maxsend > 0) {
            $this->maxsend--;
            return true;
        }
        return false;
    }
}

class mongrel2Test extends \PHPUnit_Framework_TestCase
{
    protected $conf;

    public function setUp()
    {
        $this->conf = Conf::dump();
    }

    public function tearDown()
    {
        Conf::load($this->conf);
    }

    public function testMessage()
    {
        $datafile = fopen(__DIR__ . '/../data/example.payload', 'rb');
        $front = strlen('34f9ceee-cd52-4b7f-b197-88bf2f0ec378 6 /handlertest/foo 422:{"PATH":"/handlertest/foo","user-agent":"curl/7.19.7 (i486-pc-linux-gnu) libcurl/7.19.7 OpenSSL/0.9.8k zlib/1.2.3.3 libidn/1.15","host":"localhost:6767","accept":"*/*","content-type":"multipart/form-data; boundary=----------------------------b9069e918c9e","x-forwarded-for":"::1","content-length":"21894","METHOD":"POST","VERSION":"HTTP/1.1","URI":"/handlertest/foo?toto=titi","QUERY":"toto=titi","PATTERN":"/handlertest"},21894:');
        fseek($datafile, $front, SEEK_CUR);
        $mess = new mongrel2\Message('34f9ceee-cd52-4b7f-b197-88bf2f0ec378',
                                     '6', '/handlertest/foo', (object) array(),
                                     $datafile);
        unset($mess);
        $this->assertEquals(false, is_resource($datafile));
    }

    public function testInMemoryParsing()
    {
        $message = file_get_contents(__DIR__ . '/../data/example.payload');
        $mess = mongrel2\Message::parse($message);
        $this->assertEquals($mess->path, '/handlertest/foo');
        $this->assertEquals(21894, strlen($mess->body));
    }

    public function testConnection()
    {
        $sender_id = '34f9ceee-cd52-4b7f-b197-88bf2f0ec378';
        $sub_addr = 'ipc://sub-addr';
        $pub_addr = 'ipc://pub-addr';
        $conn = new mongrel2\Connection($sender_id, $sub_addr, $pub_addr);
        $this->assertSame('ZMQSocket', get_class($conn->reqs));
        $this->assertSame('ZMQSocket', get_class($conn->resp));
        $conn->close();
    }

    public function testContextConnection()
    {
        $sender_id = '34f9ceee-cd52-4b7f-b197-88bf2f0ec378';
        $sub_addr = 'ipc://sub-addr';
        $pub_addr = 'ipc://pub-addr';
        $ctx = new \ZMQContext();
        $conn = new mongrel2\Connection($sender_id, $sub_addr, $pub_addr, $ctx);
        $this->assertSame('ZMQSocket', get_class($conn->reqs));
        $this->assertSame('ZMQSocket', get_class($conn->resp));
        $conn->close();
    }

    public function testConnectionRecv()
    {
        $sender_id = '34f9ceee-cd52-4b7f-b197-88bf2f0ec378';
        $sub_addr = 'ipc://sub-addr';
        $pub_addr = 'ipc://pub-addr';
        $conn = new mongrel2\Connection($sender_id, $sub_addr, $pub_addr);
        $conn->close();
        $conn->reqs = new DummyZMQSocket();
        $conn->reqs->setNextRecv(file_get_contents(__DIR__ . '/../data/example.payload'));
        $mess = $conn->recv();
        $this->assertEquals($mess->path, '/handlertest/foo');
    }

    public function testConnectionRecvGet()
    {
        $sender_id = '34f9ceee-cd52-4b7f-b197-88bf2f0ec378';
        $sub_addr = 'ipc://sub-addr';
        $pub_addr = 'ipc://pub-addr';
        $conn = new mongrel2\Connection($sender_id, $sub_addr, $pub_addr);
        $conn->close();
        $conn->reqs = new DummyZMQSocket();
        $conn->reqs->setNextRecv('34f9ceee-cd52-4b7f-b197-88bf2f0ec378 6 /handlertest/foo 421:{"PATH":"/handlertest/foo","user-agent":"curl/7.19.7 (i486-pc-linux-gnu) libcurl/7.19.7 OpenSSL/0.9.8k zlib/1.2.3.3 libidn/1.15","host":"localhost:6767","accept":"*/*","content-type":"multipart/form-data; boundary=----------------------------b9069e918c9e","x-forwarded-for":"::1","content-length":"21894","METHOD":"GET","VERSION":"HTTP/1.1","URI":"/handlertest/foo?toto=titi","QUERY":"toto=titi","PATTERN":"/handlertest"},0:');
        $mess = $conn->recv();
        $this->assertEquals($mess->path, '/handlertest/foo');
    }

    public function testConnectionRecvJson()
    {
        $sender_id = '34f9ceee-cd52-4b7f-b197-88bf2f0ec378';
        $sub_addr = 'ipc://sub-addr';
        $pub_addr = 'ipc://pub-addr';
        $conn = new mongrel2\Connection($sender_id, $sub_addr, $pub_addr);
        $conn->close();
        $conn->reqs = new DummyZMQSocket();
        $conn->reqs->setNextRecv('34f9ceee-cd52-4b7f-b197-88bf2f0ec378 6 /handlertest/foo 422:{"PATH":"/handlertest/foo","user-agent":"curl/7.19.7 (i486-pc-linux-gnu) libcurl/7.19.7 OpenSSL/0.9.8k zlib/1.2.3.3 libidn/1.15","host":"localhost:6767","accept":"*/*","content-type":"multipart/form-data; boundary=----------------------------b9069e918c9e","x-forwarded-for":"::1","content-length":"21894","METHOD":"JSON","VERSION":"HTTP/1.1","URI":"/handlertest/foo?toto=titi","QUERY":"toto=titi","PATTERN":"/handlertest"},7:"HELLO"');
        $mess = $conn->recv();
        $this->assertEquals($mess->path, '/handlertest/foo');
        $this->assertEquals($mess->body, 'HELLO');
    }

    /**
     * Artificial big headers in a message to test the parser.
     */
    public function testBigHeaders()
    {
        $headers = array('METHOD' => 'JSON');
        for ($i=1; $i<=100; $i++) {
            $headers['X-Dummy-' . $i] = str_repeat(chr($i % 26 + 64), 100);
        }
        $headers = json_encode($headers);
        $msg = sprintf('34f9ceee-cd52-4b7f-b197-88bf2f0ec378 6 /handlertest/foo %d:%s,%d:%s,',  strlen($headers), $headers, 7, '"HELLO"');
        $sender_id = '34f9ceee-cd52-4b7f-b197-88bf2f0ec378';
        $sub_addr = 'ipc://sub-addr';
        $pub_addr = 'ipc://pub-addr';
        $conn = new mongrel2\Connection($sender_id, $sub_addr, $pub_addr);
        $conn->close();
        $conn->reqs = new DummyZMQSocket();
        $conn->reqs->setNextRecv($msg);
        $mess = $conn->recv();
        $this->assertEquals($mess->path, '/handlertest/foo');
        $this->assertEquals($mess->body, 'HELLO');
    }

    public function testConnectionSend()
    {
        $sender_id = '34f9ceee-cd52-4b7f-b197-88bf2f0ec378';
        $sub_addr = 'ipc://sub-addr';
        $pub_addr = 'ipc://pub-addr';
        $conn = new mongrel2\Connection($sender_id, $sub_addr, $pub_addr);
        $conn->close();
        $conn->reqs = new DummyZMQSocket();
        $conn->resp = new DummyZMQSocket();
        $req = (object) array('sender' => '34f9ceee-cd52-4b7f-b197-88bf2f0ec378',
                              'conn_id' => '6');
        $this->assertEquals(true, $conn->reply($req, 'Hello !'));
        $this->assertEquals(true, $conn->deliver('34f9ceee-cd52-4b7f-b197-88bf2f0ec378', array('1', '2', '3'), 'Hello !'));
    }

    public function testConnectionDeliver()
    {
        $sender_id = '34f9ceee-cd52-4b7f-b197-88bf2f0ec378';
        $sub_addr = 'ipc://sub-addr';
        $pub_addr = 'ipc://pub-addr';
        $conn = new mongrel2\Connection($sender_id, $sub_addr, $pub_addr);
        $conn->close();
        $conn->reqs = new DummyZMQSocket();
        $conn->resp = new DummyZMQSocket();
        $req = (object) array('sender' => '34f9ceee-cd52-4b7f-b197-88bf2f0ec378',
                              'conn_id' => '6');
        $this->assertEquals(true, $conn->reply($req, 'Hello !'));
        $connection_ids = range(1, 300);
        $this->assertEquals(true, $conn->deliver('34f9ceee-cd52-4b7f-b197-88bf2f0ec378', $connection_ids, 'Hello !'));
        $conn->resp->maxsend = 2;
        $this->assertEquals(false, $conn->deliver('34f9ceee-cd52-4b7f-b197-88bf2f0ec378', $connection_ids, 'Hello !'));
    }

}