#
# Add SQL definition of database tables
#

CREATE TABLE wp_posts
(
    ID             bigint(20)   DEFAULT 0  NOT NULL,
    post_title     text         DEFAULT '' NOT NULL,
    post_excerpt   text         DEFAULT '' NOT NULL,
    post_type      varchar(20)  DEFAULT '' NOT NULL,
    post_mime_type varchar(100) DEFAULT '' NOT NULL,
    post_name      varchar(200) DEFAULT '' NOT NULL,
    post_status    varchar(20)  DEFAULT '' NOT NULL,
    post_parent    bigint(20)   DEFAULT 0  NOT NULL,
    post_content   longtext     DEFAULT '' NOT NULL,
);

CREATE TABLE wp_term_relationships
(
    term_taxonomy_id bigint(20) DEFAULT 0 NOT NULL,
    object_id        bigint(20) DEFAULT 0 NOT NULL,
);

CREATE TABLE wp_postmeta
(
    post_id    bigint(20)   DEFAULT 0 NOT NULL,
    meta_key   varchar(255) DEFAULT NULL,
    meta_value longtext     DEFAULT NULL,
);

CREATE TABLE wp_terms
(
    term_id bigint(20) DEFAULT 0 NOT NULL,
    name    varchar(200),
    slug    varchar(200),
);

CREATE TABLE wp_yoast_indexable
(
    object_id        bigint(20) DEFAULT 0 NOT NULL,
    title            text       DEFAULT NULL,
    description      mediumtext DEFAULT NULL,
    breadcrump_title text       DEFAULT NULL,
);

CREATE TABLE sys_file
(
    wp_id bigint(20) DEFAULT 0 NOT NULL,
);

CREATE TABLE pages
(
    wp_id bigint(20) DEFAULT 0 NOT NULL,
);