CREATE DATABASE events;
USE events;

CREATE TABLE locations(longitude double, latitude, double, id int, PRIMARY KEY (id));
INSERT INTO locations(longitude, latitude, id) VALUES(-79.3832, 79.3832, 0);

CREATE TABLE thumbnails(id int, url VARCHAR(300), PRIMARY KEY (id), FOREIGN KEY (id) REFERENCES locations(id));
INSERT INTO thumbnails(id, url) VALUES(0, 'https://ih1.redbubble.net/image.24695464.0125/flat,800x800,070,f.u1.jpg');
