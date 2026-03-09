-- Seed data for development / testing.
-- Run after schema.sql.

INSERT INTO `items` (`item_name`, `author_name`, `link`, `notes`, `rating`, `flag`) VALUES
('Starry Night',          'Vincent van Gogh',   'https://en.wikipedia.org/wiki/The_Starry_Night',  'Post-impressionist oil on canvas', 92, NULL),
('Bohemian Rhapsody',     'Queen',              'https://www.youtube.com/watch?v=fJ9rUzIMcZQ',     'Iconic rock opera',                 95, 'completed'),
('1984',                  'George Orwell',      NULL,                                              'Dystopian classic',                 88, 'completed'),
('Clair de Lune',         'Claude Debussy',     NULL,                                              'Suite bergamasque mvt 3',           90, NULL),
('The Persistence of Memory', 'Salvador Dalí',  'https://en.wikipedia.org/wiki/The_Persistence_of_Memory', 'Surrealist melting clocks', 85, 'revisit');

INSERT INTO `tags` (`name`) VALUES
('painting'), ('music'), ('book'), ('classical'), ('rock'), ('surrealism');

INSERT INTO `item_tags` (`item_id`, `tag_id`) VALUES
(1, 1),  -- Starry Night -> painting
(2, 2),  -- Bohemian Rhapsody -> music
(2, 5),  -- Bohemian Rhapsody -> rock
(3, 3),  -- 1984 -> book
(4, 2),  -- Clair de Lune -> music
(4, 4),  -- Clair de Lune -> classical
(5, 1),  -- Persistence of Memory -> painting
(5, 6);  -- Persistence of Memory -> surrealism
