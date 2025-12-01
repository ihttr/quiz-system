

CREATE TABLE `answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) DEFAULT NULL,
  `question_id` int(11) DEFAULT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;



CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


-- --------------------------------------------------------

--
-- Table structure for table `leaderboard`
--

CREATE TABLE `leaderboard` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `time_taken` int(11) DEFAULT NULL,
  `attempt_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE `options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) DEFAULT NULL,
  `option_text` varchar(255) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false') DEFAULT 'multiple_choice',
  `points` int(11) DEFAULT 1,
  `hint` text DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;



-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `time_limit` int(11) DEFAULT 0,
  `shuffle_questions` tinyint(1) DEFAULT 0,
  `show_immediate_feedback` tinyint(1) DEFAULT 0,
  `allow_retakes` tinyint(1) DEFAULT 1,
  `passing_score` int(11) DEFAULT 60,
  `max_attempts` int(11) DEFAULT 0,
  `category_id` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `total_questions` int(11) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `theme_preference` enum('light','dark') DEFAULT 'light',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- --------------------------------------------------------


--
-- Table structure for table `user_progress`
--

CREATE TABLE `user_progress` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  `last_attempt_id` int(11) DEFAULT NULL,
  `best_score` int(11) DEFAULT NULL,
  `attempts_count` int(11) DEFAULT 0,
  `completed` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


--
-- Indexes for table `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `selected_option_id` (`selected_option_id`);



--
-- Indexes for table `leaderboard`
--
ALTER TABLE `leaderboard`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `options`
--
ALTER TABLE `options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_progress`
--
ALTER TABLE `user_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `last_attempt_id` (`last_attempt_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `answers`
--
ALTER TABLE `answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;


--
-- AUTO_INCREMENT for table `leaderboard`
--
ALTER TABLE `leaderboard`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `options`
--
ALTER TABLE `options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_progress`
--
ALTER TABLE `user_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

