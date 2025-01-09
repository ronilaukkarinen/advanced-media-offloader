<?php

namespace WPFitter\Aws\Api\Parser;

use WPFitter\Aws\Api\Operation;
use WPFitter\Aws\Api\StructureShape;
use WPFitter\Aws\Api\Service;
use WPFitter\Aws\Result;
use WPFitter\Aws\CommandInterface;
use WPFitter\Psr\Http\Message\ResponseInterface;
use WPFitter\Psr\Http\Message\StreamInterface;
/**
 * @internal Implements JSON-RPC parsing (e.g., DynamoDB)
 */
class JsonRpcParser extends AbstractParser
{
    use PayloadParserTrait;
    /**
     * @param Service    $api    Service description
     * @param JsonParser $parser JSON body builder
     */
    public function __construct(Service $api, ?JsonParser $parser = null)
    {
        parent::__construct($api);
        $this->parser = $parser ?: new JsonParser();
    }
    public function __invoke(CommandInterface $command, ResponseInterface $response)
    {
        $operation = $this->api->getOperation($command->getName());
        return $this->parseResponse($response, $operation);
    }
    /**
     * This method parses a response based on JSON RPC protocol.
     *
     * @param ResponseInterface $response the response to parse.
     * @param Operation $operation the operation which holds information for
     *        parsing the response.
     *
     * @return Result
     */
    private function parseResponse(ResponseInterface $response, Operation $operation)
    {
        if (null === $operation['output']) {
            return new Result([]);
        }
        $outputShape = $operation->getOutput();
        foreach ($outputShape->getMembers() as $memberName => $memberProps) {
            if (!empty($memberProps['eventstream'])) {
                return new Result([$memberName => new EventParsingIterator($response->getBody(), $outputShape->getMember($memberName), $this)]);
            }
        }
        $result = $this->parseMemberFromStream($response->getBody(), $operation->getOutput(), $response);
        return new Result(\is_null($result) ? [] : $result);
    }
    public function parseMemberFromStream(StreamInterface $stream, StructureShape $member, $response)
    {
        return $this->parser->parse($member, $this->parseJson($stream, $response));
    }
}
