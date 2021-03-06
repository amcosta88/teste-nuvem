<?php

declare(strict_types=1);

namespace TiendaNube\Checkout\Http\Controller;

use PHPUnit\Framework\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use TiendaNube\Checkout\Http\Request\Request;
use TiendaNube\Checkout\Http\Request\RequestStackInterface;
use TiendaNube\Checkout\Http\Request\ServerRequest;
use TiendaNube\Checkout\Http\Response\ResponseBuilder;
use TiendaNube\Checkout\Http\Response\Response;
use TiendaNube\Checkout\Http\Response\ResponseBuilderInterface;
use TiendaNube\Checkout\Http\Response\Stream;
use TiendaNube\Checkout\Service\Shipping\AddressService;
use TiendaNube\Checkout\Service\Shipping\AddressServiceBeta;
use TiendaNube\Checkout\Service\Shipping\AddressServiceFactory;
use TiendaNube\Checkout\Service\Shipping\AddressServiceInterface;

class CheckoutControllerTest extends TestCase
{
    /**
     * @var RequestStackInterface
     */
    private $requestStack;

    /**
     * @var ResponseBuilderInterface
     */
    private $responseBuilder;

    public function setUp()
    {
        $this->requestStack = new Request(new ServerRequest());
        $this->responseBuilder = new ResponseBuilder(new Response(), new Stream());
    }

    public function testGetAddressValidToNotBetaTester()
    {
        // expected address
        $address = [
            'address' => 'Avenida da França',
            'neighborhood' => 'Comércio',
            'city' => 'Salvador',
            'state' => 'BA',
        ];

        // mocking the address service
        $addressService = $this->createMock(AddressService::class);
        $addressService->method('getAddressByZip')->willReturn($address);

        $addressServiceFactory = $this->createMock(AddressServiceFactory::class);
        $addressServiceFactory->method('create')->willReturn($addressService);

        // getting controller instance
        $controller = $this->getControllerInstance($this->requestStack, $this->responseBuilder, $addressServiceFactory);

        // test
        $result = $controller->getAddressAction('40010000');

        // asserts
        $content = json_encode($address);

        $this->assertEquals($content, $result->getBody()->getContents());
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('application/json', $result->getHeaderLine('Content-Type'));
        $this->assertEquals(strlen($content), $result->getHeaderLine('Content-Length'));
    }

    public function testGetAddressValidToBetaTester()
    {
        // expected address
        $address = [
            "altitude" => 7.0,
            "cep" => "40010000",
            "latitude" => "-12.967192",
            "longitude" => "-38.5101976",
            "address" => "Avenida da França",
            "neighborhood" => "Comércio",
            "city" => [
                "ddd" => 71,
                "ibge" => "2927408",
                "name" => "Salvador"
            ],
            "state" => [
                "acronym" => "BA"
            ]
        ];

        // mocking the address service
        $addressService = $this->createMock(AddressServiceBeta::class);
        $addressService->method('getAddressByZip')->willReturn($address);

        $addressServiceFactory = $this->createMock(AddressServiceFactory::class);
        $addressServiceFactory->method('create')->willReturn($addressService);

        // getting controller instance
        $controller = $this->getControllerInstance($this->requestStack, $this->responseBuilder, $addressServiceFactory);

        // test
        $result = $controller->getAddressAction('40010000',$addressService);

        // asserts
        $content = json_encode($address);

        $this->assertEquals($content, $result->getBody()->getContents());
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('application/json', $result->getHeaderLine('Content-Type'));
        $this->assertEquals(strlen($content), $result->getHeaderLine('Content-Length'));
    }

    public function testGetAddressInvalidToNotBetaTester()
    {
        // mocking address service
        $addressService = $this->createMock(AddressService::class);
        $addressService->method('getAddressByZip')->willReturn(null);

        $addressServiceFactory = $this->createMock(AddressServiceFactory::class);
        $addressServiceFactory->method('create')->willReturn($addressService);

        // getting controller instance
        $controller = $this->getControllerInstance($this->requestStack, $this->responseBuilder, $addressServiceFactory);

        // test
        $result = $controller->getAddressAction('400100001', $addressService);

        // asserts
        $this->assertEquals(404, $result->getStatusCode());
        $this->assertEquals('application/json', $result->getHeaderLine('Content-Type'));
        $this->assertEquals('{"error":"The requested zipcode was not found."}', $result->getBody()->getContents());
    }

    public function testGetAddressInvalidToBetaTester()
    {
        // mocking address service
        $addressService = $this->createMock(AddressServiceBeta::class);
        $addressService->method('getAddressByZip')->willReturn(null);

        $addressServiceFactory = $this->createMock(AddressServiceFactory::class);
        $addressServiceFactory->method('create')->willReturn($addressService);

        // getting controller instance
        $controller = $this->getControllerInstance($this->requestStack, $this->responseBuilder, $addressServiceFactory);

        // test
        $result = $controller->getAddressAction('400100001',$addressService);

        // asserts
        $this->assertEquals(404,$result->getStatusCode());
        $this->assertEquals('{"error":"The requested zipcode was not found."}',$result->getBody()->getContents());
    }

    /**
     * Get a RequestStack mock object
     *
     * @param ServerRequestInterface|null $expectedRequest
     * @return MockObject
     */
    private function getRequestStackInstance(?ServerRequestInterface $expectedRequest = null)
    {
        $requestStack = $this->createMock(RequestStackInterface::class);
        $expectedRequest = $expectedRequest ?: $this->createMock(ServerRequestInterface::class);
        $requestStack->method('getCurrentRequest')->willReturn($expectedRequest);

        return $requestStack;
    }

    /**
     * Get a ResponseBuilder mock object
     *
     * @param ResponseInterface|callable|null $expectedResponse
     * @return MockObject
     */
    private function getResponseBuilderInstance($expectedResponse = null)
    {
        $responseBuilder = $this->createMock(ResponseBuilderInterface::class);

        if (is_null($expectedResponse)) {
            $expectedResponse = function ($body, $status, $headers) {
                $stream = $this->createMock(StreamInterface::class);
                $stream->method('getContents')->willReturn($body);

                $response = $this->createMock(ResponseInterface::class);
                $response->method('getBody')->willReturn($stream);
                $response->method('getStatusCode')->willReturn($status);
                $response->method('getHeaders')->willReturn($headers);

                return $response;
            };
        }

        if ($expectedResponse instanceof ResponseInterface) {
            $responseBuilder->method('buildResponse')->willReturn($expectedResponse);
        } else if (is_callable($expectedResponse)) {
            $responseBuilder->method('buildResponse')->willReturnCallback($expectedResponse);
        } else {
            throw new Exception(
                'The expectedResponse argument should be an instance (or mock) of ResponseInterface or callable.'
            );
        }

        return $responseBuilder;
    }

    /**
     * Get an instance of the controller
     *
     * @param null|RequestStackInterface $requestStack
     * @param null|ResponseBuilderInterface $responseBuilder
     * @param AddressServiceFactory $addressServiceFactory
     * @return CheckoutController
     */
    private function getControllerInstance(
        ?RequestStackInterface $requestStack = null,
        ?ResponseBuilderInterface $responseBuilder = null,
        AddressServiceFactory $addressServiceFactory
    ) {
        // mocking units
        $requestStack = $requestStack ?: $this->getRequestStackInstance();
        $responseBuilder = $responseBuilder ?: $this->getResponseBuilderInstance();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with($this->equalTo('shipping.addressServiceFactory'))->willReturn($addressServiceFactory);

        return new CheckoutController($container,$requestStack,$responseBuilder);
    }
}
