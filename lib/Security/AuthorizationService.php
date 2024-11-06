<?php

namespace OCA\OrganizationFolders\Security;

use OCP\IUserSession;

class AuthorizationService {
	private const VALID_VOTES = [
		VoterInterface::ACCESS_GRANTED => true,
		VoterInterface::ACCESS_DENIED => true,
		VoterInterface::ACCESS_ABSTAIN => true,
	];

	/**
	 * @var Voter[]
	 */
	private array $voters = [];

	private $strategy;

	public function __construct(private IUserSession $userSession) {
		$this->strategy = new AffirmativeStrategy();
	}

	public function registerVoter(Voter $voter): self {
		$this->voters[] = $voter;
		return $this;
	}

	public function isGranted(array $attributes, $subject) {
		return $this->strategy->decide(
			$this->collectResults($attributes, $subject)
		);
	}

	private function collectResults(array $attributes, $subject): \Traversable {
		$user = $this->userSession->getUser();

		foreach ($this->voters as $voter) {
			$result = $voter->vote($user, $subject, $attributes);
			if (!\is_int($result) || !(self::VALID_VOTES[$result] ?? false)) {
				throw new \LogicException(sprintf('"%s::vote()" must return one of "%s" constants ("ACCESS_GRANTED", "ACCESS_DENIED" or "ACCESS_ABSTAIN"), "%s" returned.', get_debug_type($voter), VoterInterface::class, var_export($result, true)));
			}

			yield $result;
		}
	}
}
