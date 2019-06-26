CREATE TABLE IF NOT EXiSTS komtet_kassa_delivery
(
    id int not null auto_increment,
    order_id int not null,
    kk_id int,
    request text,
    response text,
    PRIMARY KEY (id)
)
engine = MyISAM
default character set = utf8;
