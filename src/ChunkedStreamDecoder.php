<?php

namespace React\HttpClient;

use Evenement\EventEmitterTrait;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;

class ChunkedStreamDecoder
{
    const CRLF = "\r\n";

    use EventEmitterTrait;

    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * @var int
     */
    protected $remainingLength = 0;

    /**
     * @var bool
     */
    protected $nextChunkIsLength = true;

    /**
     * @var ReadableStreamInterface
     */
    protected $stream;

    /**
     * @param ReadableStreamInterface $stream
     */
    public function __construct(ReadableStreamInterface $stream)
    {
        $this->stream = $stream;
        $this->stream->on('data', array($this, 'handleData'));
        Util::forwardEvents($this->stream, $this, [
            'error',
        ]);
    }

    public function handleData($data)
    {
        $this->buffer .= $data;

        do {
            $this->iterateBuffer();
        } while (strlen($this->buffer) > 0 && strpos($this->buffer, static::CRLF) !== false);
    }

    protected function iterateBuffer()
    {
        if ($this->nextChunkIsLength) {
            $this->nextChunkIsLength = false;
            $crlfPosition = strpos($this->buffer, static::CRLF);
            $lengthChunk = substr($this->buffer, 0, $crlfPosition);
            if (strpos($lengthChunk, ';') !== false) {
                list($lengthChunk) = explode(';', $lengthChunk, 2);
            }
            $this->remainingLength = hexdec($lengthChunk);
            $this->buffer = substr($this->buffer, $crlfPosition + 2);
        }

        if ($this->remainingLength > 0) {
            $chunkLength = $this->getChunkLength();
            if ($chunkLength === 0) {
                return;
            }
            $this->emit('data', array(
                substr($this->buffer, 0, $chunkLength),
                $this
            ));
            $this->remainingLength -= $chunkLength;
            $this->buffer = substr($this->buffer, $chunkLength);
            return;
        }

        $this->nextChunkIsLength = true;
        $this->buffer = substr($this->buffer, 2);
    }

    protected function getChunkLength()
    {
        $bufferLength = strlen($this->buffer);

        if ($bufferLength >= $this->remainingLength) {
            return $this->remainingLength;
        }

        return $bufferLength;
    }

    public function end($data = null)
    {
        $this->stream->end($data);
    }

    public function pause()
    {
        $this->stream->pause();
    }

    public function resume()
    {
        $this->stream->resume();
    }
}
