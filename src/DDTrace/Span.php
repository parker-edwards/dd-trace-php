<?php

namespace DDTrace;

use DDTrace\Integrations\Integration;
use DDTrace\Contracts\Span as SpanInterface;
use DDTrace\Data\Span as SpanData;

use DDTrace\Contracts\SpanContext as SpanContextInterface;
use DDTrace\Exceptions\InvalidSpanArgument;
use DDTrace\Http\Urls;
use Exception;
use InvalidArgumentException;
use Throwable;

final class Span extends SpanData implements SpanInterface
{
    /**
     * Span constructor.
     * @param string $operationName
     * @param SpanContextData $context
     * @param string $service
     * @param string $resource
     * @param int|null $startTime
     */
    public function __construct(
        $operationName,
        SpanContextData $context,
        $service,
        $resource,
        $startTime = null
    ) {
        parent::__construct($operationName, $context, $service, $resource, $startTime);
    }

    /**
     * {@inheritdoc}
     */
    public function getTraceId()
    {
        return $this->context->traceId;
    }

    /**
     * {@inheritdoc}
     */
    public function getSpanId()
    {
        return $this->context->spanId;
    }

    /**
     * {@inheritdoc}
     */
    public function getParentId()
    {
        return $this->context->parentId;
    }

    /**
     * {@inheritdoc}
     */
    public function overwriteOperationName($operationName)
    {
        $this->operationName = $operationName;
    }

    /**
     * {@inheritdoc}
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * {@inheritdoc}
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * {@inheritdoc}
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * {@inheritdoc}
     */
    public function setTag($key, $value, $setIfFinished = false)
    {
        if ($this->isFinished() && !$setIfFinished) {
            return;
        }

        if ($key !== (string)$key) {
            throw InvalidSpanArgument::forTagKey($key);
        }

        if ($key === Tag::ERROR) {
            $this->setError($value);
            return;
        }

        if ($key === Tag::SERVICE_NAME) {
            $this->service = $value;
            return;
        }

        if ($key === Tag::RESOURCE_NAME) {
            $this->resource = (string)$value;
            return;
        }

        if ($key === Tag::SPAN_TYPE) {
            $this->type = $value;
            return;
        }

        if ($key === Tag::HTTP_URL) {
            $value = Urls::sanitize((string)$value);
        }

        if ($key === Tag::HTTP_STATUS_CODE && $value >= 500) {
            $this->hasError = true;
            if (!isset($this->tags[Tag::ERROR_TYPE])) {
                $this->tags[Tag::ERROR_TYPE] = 'Internal Server Error';
            }
        }

        if (in_array($key, self::getMetricsNames())) {
            $this->setMetric($key, $value);
            return;
        }

        $this->tags[$key] = (string)$value;
    }

    /**
     * {@inheritdoc}
     */
    public function getTag($key)
    {
        if (array_key_exists($key, $this->tags)) {
            return $this->tags[$key];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllTags()
    {
        return $this->tags;
    }

    /**
     * {@inheritdoc}
     */
    public function hasTag($name)
    {
        return array_key_exists($name, $this->getAllTags());
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function setMetric($key, $value)
    {
        if ($key === Tag::ANALYTICS_KEY) {
            $this->processTraceAnalyticsTag($value);
            return;
        }

        $this->metrics[$key] = $value;
    }

    /**
     * @return array
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * @return string[] The known metrics names
     */
    private static function getMetricsNames()
    {
        return [
            Tag::ANALYTICS_KEY,
        ];
    }

    /**
     * @param bool|float $value
     */
    private function processTraceAnalyticsTag($value)
    {
        if (true === $value || null === $value) {
            $this->metrics[Tag::ANALYTICS_KEY] = 1.0;
        } elseif (false === $value) {
            unset($this->metrics[Tag::ANALYTICS_KEY]);
        } elseif (is_numeric($value) && 0 <= $value && $value <= 1) {
            $this->metrics[Tag::ANALYTICS_KEY] = (float)$value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setResource($resource)
    {
        $this->setTag(Tag::RESOURCE_NAME, $resource);
    }

    /**
     * Stores a Throwable object within the span tags. The error status is
     * updated and the error.Error() string is included with a default tag key.
     * If the Span has been finished, it will not be modified by this method.
     *
     * @param Throwable|Exception|bool|string|null $error
     * @throws InvalidArgumentException
     */
    public function setError($error)
    {
        if ($this->isFinished()) {
            return;
        }

        if (($error instanceof Exception) || ($error instanceof Throwable)) {
            $this->hasError = true;
            $this->tags[Tag::ERROR_MSG] = $error->getMessage();
            $this->tags[Tag::ERROR_TYPE] = get_class($error);
            $this->tags[Tag::ERROR_STACK] = $error->getTraceAsString();
            return;
        }

        if (is_bool($error)) {
            $this->hasError = $error;
            return;
        }

        if (is_null($error)) {
            $this->hasError = false;
        }

        throw InvalidSpanArgument::forError($error);
    }

    /**
     * @param string $message
     * @param string $type
     */
    public function setRawError($message, $type)
    {
        if ($this->isFinished()) {
            return;
        }

        $this->hasError = true;
        $this->tags[Tag::ERROR_MSG] = $message;
        $this->tags[Tag::ERROR_TYPE] = $type;
    }

    public function hasError()
    {
        return $this->hasError;
    }

    /**
     * {@inheritdoc}
     */
    public function finish($finishTime = null)
    {
        if ($this->isFinished()) {
            return;
        }

        $this->duration = ($finishTime ?: Time::now()) - $this->startTime;
    }

    /**
     * @param Throwable|Exception $error
     * @return void
     */
    public function finishWithError($error)
    {
        $this->setError($error);
        $this->finish();
    }

    /**
     * {@inheritdoc}
     */
    public function isFinished()
    {
        return $this->duration !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function getOperationName()
    {
        return $this->operationName;
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function log(array $fields = [], $timestamp = null)
    {
        foreach ($fields as $key => $value) {
            if ($key === Tag::LOG_EVENT && $value === Tag::ERROR) {
                $this->setError(true);
            } elseif ($key === Tag::LOG_ERROR || $key === Tag::LOG_ERROR_OBJECT) {
                $this->setError($value);
            } elseif ($key === Tag::LOG_MESSAGE) {
                $this->setTag(Tag::ERROR_MSG, $value);
            } elseif ($key === Tag::LOG_STACK) {
                $this->setTag(Tag::ERROR_STACK, $value);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addBaggageItem($key, $value)
    {
        $this->context = $this->context->withBaggageItem($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getBaggageItem($key)
    {
        return $this->context->getBaggageItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getAllBaggageItems()
    {
        return $this->context->baggageItems;
    }

    /**
     * {@inheritdoc}
     *
     * @param Integration $integration
     * @return self
     */
    public function setIntegration(Integration $integration)
    {
        $this->integration = $integration;
        return $this;
    }

    /**
     * @return null|Integration
     */
    public function getIntegration()
    {
        return $this->integration;
    }

    /**
     * @param bool $value
     * @return self
     */
    public function setTraceAnalyticsCandidate($value = true)
    {
        $this->isTraceAnalyticsCandidate = $value;
        return $this;
    }

    /**
     * @return bool
     */
    public function isTraceAnalyticsCandidate()
    {
        return $this->isTraceAnalyticsCandidate;
    }
}
