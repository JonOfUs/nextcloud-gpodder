<?php
declare(strict_types=1);

namespace OCA\GPodderSync\Core\EpisodeAction;

use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCA\GPodderSync\Db\EpisodeAction\EpisodeActionEntity;
use OCA\GPodderSync\Db\EpisodeAction\EpisodeActionRepository;
use OCA\GPodderSync\Db\EpisodeAction\EpisodeActionWriter;
use OCP\DB\Exception;

class EpisodeActionSaver
{

	private EpisodeActionRepository $episodeActionRepository;
	private EpisodeActionWriter $episodeActionWriter;
	private EpisodeActionReader $episodeActionReader;

	const DATETIME_FORMAT = 'Y-m-d\TH:i:s';

	public function __construct(
		EpisodeActionRepository $episodeActionRepository,
		EpisodeActionWriter $episodeActionWriter,
		EpisodeActionReader $episodeActionReader
	)
	{
		$this->episodeActionRepository = $episodeActionRepository;
		$this->episodeActionWriter = $episodeActionWriter;
		$this->episodeActionReader = $episodeActionReader;
	}

	/**
	 * @param array $episodeActionsArray
	 *
	 * @return void
	 */
	public function saveEpisodeActions($episodeActionsArray, string $userId): void
	{
		$episodeActions = $this->episodeActionReader->fromArray($episodeActionsArray);

        foreach ($episodeActions as $episodeAction) {
			$episodeActionEntity = $this->hydrateEpisodeActionEntity($episodeAction, $userId);

			try {
                $this->episodeActionWriter->save($episodeActionEntity);
            } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
                $this->updateEpisodeAction($episodeActionEntity, $userId);
            } catch (Exception $exception) {
                if ($exception->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                    $this->updateEpisodeAction($episodeActionEntity, $userId);
                }
            }
        }
	}

	private function convertTimestampToUnixEpoch(string $timestamp): string
	{
		return \DateTime::createFromFormat('D F d H:i:s T Y', $timestamp)
			->setTimezone(new DateTimeZone('UTC'))
			->format("U");
	}

	private function updateEpisodeAction(
		EpisodeActionEntity $episodeActionEntity,
		string $userId
	): EpisodeActionEntity
	{
		$identifier = $episodeActionEntity->getGuid() ?? $episodeActionEntity->getEpisode();
		$episodeActionToUpdate = $this->episodeActionRepository->findByEpisodeIdentifier(
			$identifier,
			$userId
		);

		if ($episodeActionToUpdate === null && $episodeActionEntity->getGuid() !== null) {
			$episodeActionToUpdate = $this->getOldEpisodeActionByEpisodeUrl($episodeActionEntity->getEpisode(), $userId);
		}

		$episodeActionEntity->setId($episodeActionToUpdate->getId());

		$this->ensureGuidDoesNotGetNulledWithOldData($episodeActionToUpdate, $episodeActionEntity);

		return $this->episodeActionWriter->update($episodeActionEntity);
	}

	private function getOldEpisodeActionByEpisodeUrl(string $episodeUrl, string $userId): ?EpisodeAction
	{
		return $this->episodeActionRepository->findByEpisodeIdentifier(
			$episodeUrl,
			$userId
		);
	}

	private function ensureGuidDoesNotGetNulledWithOldData(EpisodeAction $episodeActionToUpdate, EpisodeActionEntity $episodeActionEntity): void
	{
		$existingGuid = $episodeActionToUpdate->getGuid();
		if ($existingGuid !== null && $episodeActionEntity->getGuid() == null) {
			$episodeActionEntity->setGuid($existingGuid);
		}
	}

	private function hydrateEpisodeActionEntity(EpisodeAction $episodeAction, string $userId): EpisodeActionEntity
	{
		$episodeActionEntity = new EpisodeActionEntity();
		$episodeActionEntity->setPodcast($episodeAction->getPodcast());
		$episodeActionEntity->setEpisode($episodeAction->getEpisode());
		$episodeActionEntity->setGuid($episodeAction->getGuid());
		$episodeActionEntity->setAction($episodeAction->getAction());
		$episodeActionEntity->setPosition($episodeAction->getPosition());
		$episodeActionEntity->setStarted($episodeAction->getStarted());
		$episodeActionEntity->setTotal($episodeAction->getTotal());
		$episodeActionEntity->setTimestampEpoch($this->convertTimestampToUnixEpoch($episodeAction->getTimestamp()));
		$episodeActionEntity->setUserId($userId);

		return $episodeActionEntity;
	}
}
