<?php

namespace React\Promise;

/**
 * @deprecated 2.8.0 External usage of RejectedPromise is deprecated, use `reject()` instead.
 */
class RejectedPromise implements ExtendedPromiseInterface, CancellablePromiseInterface
{
    private static $tracer;

    private $reason;

    public static function setTracer($tracer)
    {
        self::$tracer = $tracer;
    }

    public static function getTracer()
    {
        return self::$tracer;
    }

    public function __construct($reason = null)
    {
        if ($reason instanceof PromiseInterface) {
            throw new \InvalidArgumentException('You cannot create React\Promise\RejectedPromise with a promise. Use React\Promise\reject($promiseOrValue) instead.');
        }

        $this->reason = $reason;

        if (null !== $tracer = self::$tracer) {
            $tracer->instanciated($this, $reason);
        }
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null === $onRejected) {
            return $this;
        }

        if (null !== $tracer = self::$tracer) {
            $tracer->handled($this);
        }

        try {
            return resolve($onRejected($this->reason));
        } catch (\Throwable $exception) {
            return new RejectedPromise($exception);
        } catch (\Exception $exception) {
            return new RejectedPromise($exception);
        }
    }

    public function done(callable $onFulfilled = null, callable $onRejected = null, callable $onProgress = null)
    {
        if (null !== $tracer = self::$tracer) {
            $tracer->handled($this);
        }

        if (null === $onRejected) {
            throw UnhandledRejectionException::resolve($this->reason);
        }

        $result = $onRejected($this->reason);

        if ($result instanceof self) {
            if (null !== $tracer = self::$tracer) {
                $tracer->handled($this);
            }
            throw UnhandledRejectionException::resolve($result->reason);
        }

        if ($result instanceof ExtendedPromiseInterface) {
            $result->done();
        }
    }

    public function otherwise(callable $onRejected)
    {
        if (!_checkTypehint($onRejected, $this->reason)) {
            return $this;
        }

        return $this->then(null, $onRejected);
    }

    public function always(callable $onFulfilledOrRejected)
    {
        return $this->then(null, function ($reason) use ($onFulfilledOrRejected) {
            return resolve($onFulfilledOrRejected())->then(function () use ($reason) {
                return new RejectedPromise($reason);
            });
        });
    }

    public function progress(callable $onProgress)
    {
        return $this;
    }

    public function cancel()
    {
    }

    public function __destruct()
    {
        if (null !== $tracer = self::$tracer) {
            $tracer->destroyed($this);
        }
    }
}
