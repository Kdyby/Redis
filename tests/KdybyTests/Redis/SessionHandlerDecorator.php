<?php

declare(strict_types = 1);

namespace KdybyTests\Redis;

class SessionHandlerDecorator implements \SessionHandlerInterface
{

	/**
	 * @var array
	 */
	public $series = [];

	/**
	 * @var bool
	 */
	public $log = FALSE;

	/**
	 * @var array
	 */
	public $openedSessionCalls = [];

	/**
	 * @var \SessionHandlerInterface
	 */
	private $handler;

	public function __construct(\SessionHandlerInterface $handler)
	{
		$this->handler = $handler;
	}

	private function log(string $msg): void
	{
		if ( ! $this->log) {
			return;
		}

		\file_put_contents(
			\dirname(TEMP_DIR) . '/session.log',
			\sprintf(
				'[%s] [%s]: %s',
				\date('Y-m-d H:i:s'),
				\str_pad((string) \getmypid(), 6, '0', STR_PAD_LEFT),
				$msg
			)
			. "\n",
			FILE_APPEND
		);
	}

	/**
	 * @param string $savePath
	 * @param string $sessionId
	 */
	// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	public function open($savePath, $sessionId): bool
	{
		$this->log(\sprintf('%s: %s', __FUNCTION__, $sessionId));
		$this->openedSessionCalls[] = \array_merge([__FUNCTION__], \func_get_args());

		return $this->handler->open($savePath, $sessionId);
	}

	public function close(): bool
	{
		$this->log(__FUNCTION__);
		$this->openedSessionCalls[] = \array_merge([__FUNCTION__], \func_get_args());
		$this->series[] = $this->openedSessionCalls;
		$this->openedSessionCalls = [];

		return $this->handler->close();
	}

	/**
	 * @param string $sessionId
	 * @return string|false
	 * @throws \Throwable
	 */
	// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	#[\ReturnTypeWillChange]
	public function read($sessionId)
	{
		$this->log(\sprintf('%s: %s', __FUNCTION__, $sessionId));
		$this->openedSessionCalls[] = \array_merge([__FUNCTION__], \func_get_args());
		try {
			return $this->handler->read($sessionId);
		} catch (\Throwable $e) {
			$this->log(\sprintf('%s: %s', __FUNCTION__, $e->getMessage()));
			throw $e;
		}
	}

	/**
	 * @param string $sessionId
	 * @throws \Throwable
	 */
	// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	public function destroy($sessionId): bool
	{
		$this->log(\sprintf('%s: %s', __FUNCTION__, $sessionId));
		$this->openedSessionCalls[] = \array_merge([__FUNCTION__], \func_get_args());
		try {
			return $this->handler->destroy($sessionId);
		} catch (\Throwable $e) {
			$this->log(\sprintf('%s: %s', __FUNCTION__, $e->getMessage()));
			throw $e;
		}
	}

	/**
	 * @param string $sessionId
	 * @param string $sessionData
	 * @throws \Throwable
	 */
	// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	public function write($sessionId, $sessionData): bool
	{
		$this->log(\sprintf('%s: %s', __FUNCTION__, $sessionId));
		$this->openedSessionCalls[] = \array_merge([__FUNCTION__], \func_get_args());
		try {
			return $this->handler->write($sessionId, $sessionData);
		} catch (\Throwable $e) {
			$this->log(\sprintf('%s: %s', __FUNCTION__, $e->getMessage()));
			throw $e;
		}
	}

	/**
	 * @param int $maxlifetime
	 * @return int|false
	 */
	// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	#[\ReturnTypeWillChange]
	public function gc($maxlifetime)
	{
		$this->log(__FUNCTION__);
		$this->openedSessionCalls[] = \array_merge([__FUNCTION__], \func_get_args());

		return $this->handler->gc($maxlifetime);
	}

}
