<?php

namespace rewish\craft\mcp\http\fetch;

/**
 * A URL the server will not fetch, or a response it will not accept. The
 * message is meant for the caller: it says which rule refused and why.
 */
final class FetchRefused extends \RuntimeException {}
