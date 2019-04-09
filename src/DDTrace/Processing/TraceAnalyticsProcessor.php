<?php

namespace DDTrace\Processing;

use DDTrace\Data\Span as SpanData;
use DDTrace\Tag;

/**
 * A span processor in charge of adding the trace analytics client config metric when appropriate.
 *
 * NOTE: this may be transformer into a filter for consistency with other tracers, but for now we did not implement
 * any filtering functionality so giving it such name as of now might be misleading.
 */
final class TraceAnalyticsProcessor
{
    /**
     * Process the span adding the trace analytics client config option when appropriate.
     *
     * @param Span $span
     */
    public function process(SpanData $span)
    {
        // We only consider spans that are marked as trace analytics candidates, otherwise the customer bill would
        // explode because we are sampling not relevant spans.
        if (!$span->isTraceAnalyticsCandidate) {
            return;
        }

        // We only process spans that are generated by an integration, no custom span. Being part of an integration
        // makes them configurable via the usual config options.
        if (null === $span->integration) {
            return;
        }

        // If a trace analytics tag has already been set, then we honor it.
        if (array_key_exists(Tag::ANALYTICS_KEY, $span->tags)) {
            return;
        }

        $integration = $span->integration;
        if (null !== $integration && $integration->isTraceAnalyticsEnabled()) {
            $span->tags[Tag::ANALYTICS_KEY] = $integration->getTraceAnalyticsSampleRate();
        }
    }
}
