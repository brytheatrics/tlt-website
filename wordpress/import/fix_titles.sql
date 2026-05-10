-- TLT title cleanup — run once in Local's Adminer/phpMyAdmin
-- Updates each show title by matching against the stored legacy URL meta.

UPDATE wp_posts SET post_title = 'KAY MEIER, SECRETARY'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/board/kay'
);
UPDATE wp_posts SET post_title = 'JUDY ZERZAN-THUL'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/board/kay-emp99'
);
UPDATE wp_posts SET post_title = 'ATHENA HITSON, CO-TREASURER'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/board/kay-njsdj'
);
UPDATE wp_posts SET post_title = 'LIBBY LINDSTROM'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/board/kay-rdzag'
);
UPDATE wp_posts SET post_title = 'LEAH HOLE-MARSHALL'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/board/leah'
);
UPDATE wp_posts SET post_title = 'TREVOR OWENS, CO-TREASURER'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/board/trevor'
);
UPDATE wp_posts SET post_title = '95th Season'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/category/95th+Season'
);
UPDATE wp_posts SET post_title = '96th Season'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/category/96th+Season'
);
UPDATE wp_posts SET post_title = 'Board'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/category/Board'
);
UPDATE wp_posts SET post_title = 'Education Program'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/category/Education+Program'
);
UPDATE wp_posts SET post_title = 'Jobs'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/category/Jobs'
);
UPDATE wp_posts SET post_title = 'Off the Shelf'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/category/Off+the+Shelf'
);
UPDATE wp_posts SET post_title = 'Press'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/category/Press'
);
UPDATE wp_posts SET post_title = 'Staff'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/category/Staff'
);
UPDATE wp_posts SET post_title = 'Auditions'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/auditions'
);
UPDATE wp_posts SET post_title = 'Board of Directors & Staff'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/board-and-staff'
);
UPDATE wp_posts SET post_title = 'ClubTLT'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/clubtlt'
);
UPDATE wp_posts SET post_title = 'Contact & Transportation'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/contact'
);
UPDATE wp_posts SET post_title = 'Tacoma Little Theatre'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/cover'
);
UPDATE wp_posts SET post_title = 'About the Program'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/education'
);
UPDATE wp_posts SET post_title = 'Flush Campaign'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/flush'
);
UPDATE wp_posts SET post_title = 'History'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/history'
);
UPDATE wp_posts SET post_title = 'Home'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/home'
);
UPDATE wp_posts SET post_title = 'Job Openings'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/job-openings'
);
UPDATE wp_posts SET post_title = 'OFF THE SHELF'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/off-the-shelf'
);
UPDATE wp_posts SET post_title = 'Parking Information'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/parking-information'
);
UPDATE wp_posts SET post_title = 'Press'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/press'
);
UPDATE wp_posts SET post_title = 'Recorded Programs'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/recorded-programs'
);
UPDATE wp_posts SET post_title = 'STUDENTS ON STAGE'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/students-on-stage'
);
UPDATE wp_posts SET post_title = 'Ticket Information & Policies'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/ticketinfo'
);
UPDATE wp_posts SET post_title = 'Volunteer'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/volunteer'
);
UPDATE wp_posts SET post_title = '1918-1930'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/1918-1930'
);
UPDATE wp_posts SET post_title = '1930-1940'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/1930-1940'
);
UPDATE wp_posts SET post_title = '1940-1950'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/1940-1950'
);
UPDATE wp_posts SET post_title = '1950-1960'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/1950-1960'
);
UPDATE wp_posts SET post_title = '1960-1970'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/1960-1970'
);
UPDATE wp_posts SET post_title = '1970-1980'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/1970-1980'
);
UPDATE wp_posts SET post_title = '1980-1990'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/1980-1990'
);
UPDATE wp_posts SET post_title = '1990-2000'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/1990-2000'
);
UPDATE wp_posts SET post_title = '2000-2010'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/2000-2010'
);
UPDATE wp_posts SET post_title = '2010-2011'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/2010-2011'
);
UPDATE wp_posts SET post_title = '2011-2012'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/2011-2012'
);
UPDATE wp_posts SET post_title = '2012-2013'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/2012-2013'
);
UPDATE wp_posts SET post_title = 'Bell book and candle'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20182019/bellbookcandle'
);
UPDATE wp_posts SET post_title = 'A Doll''s House'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20182019/dollshouse'
);
UPDATE wp_posts SET post_title = 'Foreigner'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20182019/foreigner'
);
UPDATE wp_posts SET post_title = 'Hay Fever'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20182019/hayfever'
);
UPDATE wp_posts SET post_title = 'Laura'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20182019/laura'
);
UPDATE wp_posts SET post_title = 'A Little Night Music'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20182019/nightmusic'
);
UPDATE wp_posts SET post_title = 'Scrooge the musical'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20182019/scrooge'
);
UPDATE wp_posts SET post_title = 'Sophie'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20182019/sophie'
);
UPDATE wp_posts SET post_title = 'Calendar girls'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20192020/calendargirls'
);
UPDATE wp_posts SET post_title = 'A chorus line'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20192020/chorusline'
);
UPDATE wp_posts SET post_title = 'Terms of endearment'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20192020/chorusline-fnlh3'
);
UPDATE wp_posts SET post_title = 'Manchurian Candidate'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20192020/chorusline-fnlh3-akhpz'
);
UPDATE wp_posts SET post_title = 'Evil dead'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20192020/evildead'
);
UPDATE wp_posts SET post_title = 'Holmes for the Holidays'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20192020/holmes'
);
UPDATE wp_posts SET post_title = 'Twas the night before christmas'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20192020/holmes-gaf4k'
);
UPDATE wp_posts SET post_title = 'Shattering'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20192020/shattering'
);
UPDATE wp_posts SET post_title = 'Clue'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20212022/clue'
);
UPDATE wp_posts SET post_title = 'A chorus line'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20212022/clue-633y7'
);
UPDATE wp_posts SET post_title = 'Silent sky'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20212022/clue-anzln'
);
UPDATE wp_posts SET post_title = 'Happiest Song Plays Last'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20212022/clue-anzln-g52ct'
);
UPDATE wp_posts SET post_title = 'Luck of the Irish'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20212022/clue-anzln-g52ct-dwpf6'
);
UPDATE wp_posts SET post_title = 'Wizard of oz'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20212022/clue-rhe7l'
);
UPDATE wp_posts SET post_title = 'Terms of Endearment'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20212022/terms'
);
UPDATE wp_posts SET post_title = 'Murder on the Orient Express'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20222023/murder'
);
UPDATE wp_posts SET post_title = 'Po Boy Tango'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20222023/poboy'
);
UPDATE wp_posts SET post_title = 'Rock of Ages'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20222023/rock'
);
UPDATE wp_posts SET post_title = '2026-2027 SEASON TICKETS'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20222023/seasontix'
);
UPDATE wp_posts SET post_title = 'The Shawshank Redemption'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20222023/shawshank'
);
UPDATE wp_posts SET post_title = 'Significant Other'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20222023/significant'
);
UPDATE wp_posts SET post_title = 'Steel Magnolias'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20222023/steel'
);
UPDATE wp_posts SET post_title = 'A Christmas Story'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20222023/xmasstory'
);
UPDATE wp_posts SET post_title = 'ALMOST, MAINE'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20232024/almostmaine'
);
UPDATE wp_posts SET post_title = 'A DOLL''S HOUSE, PART 2'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20232024/dollshouse2'
);
UPDATE wp_posts SET post_title = 'MISERY'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20232024/misery'
);
UPDATE wp_posts SET post_title = 'FROM THE MISSISSIPPI DELTA'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20232024/mississippi'
);
UPDATE wp_posts SET post_title = 'RENT'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20232024/rent'
);
UPDATE wp_posts SET post_title = 'RUDOLPH THE RED-NOSED REINDEER'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20232024/rudolph'
);
UPDATE wp_posts SET post_title = 'THE PLAY THAT GOES WRONG'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20232024/wrong'
);
UPDATE wp_posts SET post_title = 'BUG'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20242025/bug'
);
UPDATE wp_posts SET post_title = 'THE CURIOUS INCIDENT OF THE DOG IN THE NIGHT-TIME'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20242025/curious'
);
UPDATE wp_posts SET post_title = 'LORCA IN A GREEN DRESS'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20242025/curious-y7xjc'
);
UPDATE wp_posts SET post_title = 'FIDDLER ON THE ROOF'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20242025/fiddler'
);
UPDATE wp_posts SET post_title = 'THE MOUSETRAP'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20242025/mousetrap'
);
UPDATE wp_posts SET post_title = 'ONE MAN, TWO GUVNORS'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20242025/oneman'
);
UPDATE wp_posts SET post_title = 'ROCKY'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20242025/rocky'
);
UPDATE wp_posts SET post_title = 'BEDROOM FARCE'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20252026/bedroom'
);
UPDATE wp_posts SET post_title = 'THE DA VINCI CODE'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20252026/davinci'
);
UPDATE wp_posts SET post_title = 'MATILDA'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20252026/matilda'
);
UPDATE wp_posts SET post_title = 'NOW HIRING-PRODUCTION TEAM MEMBERS FOR 2026-2027'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20252026/productionteam'
);
UPDATE wp_posts SET post_title = 'SOTTO VOCE'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20252026/sotto'
);
UPDATE wp_posts SET post_title = 'SPRING AWAKENING'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20252026/spring'
);
UPDATE wp_posts SET post_title = 'THE MOUNTAINTOP'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20252026/spring-bpb9w'
);
UPDATE wp_posts SET post_title = 'THE TIME MACHINE'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/20252026/timemachine'
);
UPDATE wp_posts SET post_title = 'A Midsummer Nights Dream'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/a-midsummer-nights-dream'
);
UPDATE wp_posts SET post_title = 'Amazon Smile'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/amazon-smile'
);
UPDATE wp_posts SET post_title = 'Bye Bye Birdie'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/bye-bye-birdie'
);
UPDATE wp_posts SET post_title = 'Cabaret Pictures'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/cabaret-pictures-1'
);
UPDATE wp_posts SET post_title = 'Fred Meyer Community Rewards'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/fred-meyer-community-rewards-helps-tlt'
);
UPDATE wp_posts SET post_title = 'Current managing artistic director, links says it is from'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/managing-artistic-director'
);
UPDATE wp_posts SET post_title = 'Current Box Office Lead'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/office-manager-373ag-9pnef-aclg5-6l825'
);
UPDATE wp_posts SET post_title = 'MARIA-TANIA BANDES B. WEINGARDEN, PRESIDENT'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/tania'
);
UPDATE wp_posts SET post_title = 'Complete Works of'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/the-complete-works-of-william-shakespeare-abridged-revised'
);
UPDATE wp_posts SET post_title = 'Fox on the Fairway'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/the-fox-on-the-fairway'
);
UPDATE wp_posts SET post_title = 'Great Gatsby'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/the-great-gatsby'
);
UPDATE wp_posts SET post_title = 'Last Night of Ballyhoo'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/the-last-night-of-ballyhoo'
);
UPDATE wp_posts SET post_title = 'Weir'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/the-weir'
);
UPDATE wp_posts SET post_title = 'Vanya and Sonia and Masha and Spike'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2015/vanya-sonia-masha-and-spike'
);
UPDATE wp_posts SET post_title = 'Mice and Men'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2016/of-mice-and-men'
);
UPDATE wp_posts SET post_title = 'Parking'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2016/parking'
);
UPDATE wp_posts SET post_title = 'Man Who Shot Liberty Valance'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2016/the-man-who-shot-liberty-valance-1'
);
UPDATE wp_posts SET post_title = 'Underpants'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2016/the-underpants'
);
UPDATE wp_posts SET post_title = 'Seussical'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2016/the-underpants-5ypj7-em9m8'
);
UPDATE wp_posts SET post_title = 'JALEN C. PENN'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2021/russell-5tgd7-fbr6k'
);
UPDATE wp_posts SET post_title = 'ASHLEY YOUNG'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2021/russell-wgwef'
);
UPDATE wp_posts SET post_title = 'TLT WINS NATIONAL AWARD'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2021/tlt-wins-national-award'
);
UPDATE wp_posts SET post_title = 'SEASON 2020-2021'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2022/season-2020-2021'
);
UPDATE wp_posts SET post_title = 'GRAB DINNER AT HARBOR LIGHTS!'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/2025/grab-dinner-at-harbor-lights'
);
UPDATE wp_posts SET post_title = 'REQUEST AN AUCTION DONATION'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/donationrequest'
);
UPDATE wp_posts SET post_title = '2010-11 Shows'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2010-2011'
);
UPDATE wp_posts SET post_title = '2012-2013 Shows'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2010-2020'
);
UPDATE wp_posts SET post_title = '2011-12 Shows'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2011-2012'
);
UPDATE wp_posts SET post_title = '2012-2013 Shows'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2012-2013'
);
UPDATE wp_posts SET post_title = '2014-15 Shows'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2013-2014'
);
UPDATE wp_posts SET post_title = '2014-15 Shows'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2014-2015'
);
UPDATE wp_posts SET post_title = '2015-16 Show'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2015-2016'
);
UPDATE wp_posts SET post_title = 'Underpants'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2016-2017'
);
UPDATE wp_posts SET post_title = '2018-19 Show'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2018-2019'
);
UPDATE wp_posts SET post_title = '2020 2021'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2020-2021'
);
UPDATE wp_posts SET post_title = 'Play That Goes Wrong'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2023-2024'
);
UPDATE wp_posts SET post_title = 'Da Vinci Code'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/tag/2025-2026'
);
UPDATE wp_posts SET post_title = 'TEAGAN MCMONAGLE, BOX OFFICE/SHOP'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = 'https://www.tacomalittletheatre.com/blog/teagan'
);
