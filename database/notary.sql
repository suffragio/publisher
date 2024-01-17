SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `area` (
  `publication` int(11) NOT NULL,
  `name` varchar(1024) NOT NULL,
  `polygons` multipolygon NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `certificate` (
  `publication` int(11) NOT NULL,
  `app` int(11) NOT NULL,
  `appSignature` blob NOT NULL,
  `type` enum('endorse','report','update','transfer','lost','sign','trust','distrust') NOT NULL,
  `certifiedPublication` int(11) NOT NULL,
  `comment` varchar(2048) NOT NULL,
  `message` varchar(2048) NOT NULL,
  `latest` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `citizen` (
  `publication` int(11) NOT NULL,
  `app` int(11) NOT NULL,
  `appSignature` blob NOT NULL,
  `givenNames` varchar(256) NOT NULL,
  `familyName` varchar(256) NOT NULL,
  `picture` blob NOT NULL,
  `home` point NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `participant` (
  `id` int(11) NOT NULL,
  `type` enum('app','citizen','judge','notary','station') NOT NULL,
  `key` blob NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `participation` (
  `publication` int(11) NOT NULL,
  `app` int(11) NOT NULL,
  `appSignature` blob NOT NULL,
  `referendum` int(11) NOT NULL,
  `encryptedVote` blob NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposal` (
  `publication` int(11) NOT NULL,
  `area` int(11) NOT NULL,
  `title` varchar(128) NOT NULL,
  `description` text NOT NULL,
  `question` varchar(128) NOT NULL,
  `answers` text NOT NULL,
  `secret` tinyint(1) NOT NULL,
  `deadline` datetime NOT NULL,
  `trust` bigint(20) NOT NULL,
  `website` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `participants` int(11) NOT NULL,
  `corpus` int(11) NOT NULL,
  `results` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publication` (
  `id` int(11) NOT NULL,
  `version` smallint(6) NOT NULL,
  `type` enum('citizen','certificate','area','proposal','participation','vote') NOT NULL,
  `published` datetime NOT NULL,
  `signature` blob NOT NULL COMMENT 'signature of the publication by the author',
  `signatureSHA1` binary(20) GENERATED ALWAYS AS (unhex(sha(`signature`))) STORED,
  `participant` int(11) NOT NULL COMMENT 'participant id of the author'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `results` (
  `referendum` int(11) NOT NULL,
  `answer` text NOT NULL,
  `count` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vote` (
  `publication` int(11) NOT NULL,
  `app` int(11) NOT NULL,
  `appSignature` blob NOT NULL,
  `referendum` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `ballot` binary(32) NOT NULL,
  `answer` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `webservice` (
  `participant` int(11) NOT NULL,
  `url` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `area`
  ADD PRIMARY KEY (`publication`);

ALTER TABLE `certificate`
  ADD PRIMARY KEY (`publication`),
  ADD KEY `publicationId` (`certifiedPublication`),
  ADD KEY `app` (`app`);

ALTER TABLE `citizen`
  ADD PRIMARY KEY (`publication`),
  ADD KEY `app` (`app`);

ALTER TABLE `participant`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `participation`
  ADD PRIMARY KEY (`publication`),
  ADD KEY `app` (`app`),
  ADD KEY `referendum` (`referendum`);

ALTER TABLE `proposal`
  ADD PRIMARY KEY (`publication`),
  ADD KEY `area` (`area`);

ALTER TABLE `publication`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `signatureSHA1` (`signatureSHA1`),
  ADD KEY `authorId` (`participant`);

ALTER TABLE `results`
  ADD UNIQUE KEY `referendum` (`referendum`,`answer`) USING HASH;

ALTER TABLE `vote`
  ADD PRIMARY KEY (`publication`),
  ADD KEY `app` (`app`),
  ADD KEY `referendum` (`referendum`);

ALTER TABLE `webservice`
  ADD PRIMARY KEY (`participant`);


ALTER TABLE `participant`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `publication`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
