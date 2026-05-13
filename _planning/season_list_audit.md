# Season List Audit

Generated alongside `SEASON_LIST.md`. Read-only sanity checks.

## Typo'd year ranges fixed

Server filenames with `(end - start) != 1`. Treated as typos and merged into the season starting at the first year.

- `1940-1940 Tovarich.pdf` (1940-1940) → merged into `1940-1941`
- `1958-1958 DICK ODLIN VARIETY SHOW.pdf` (1958-1958) → merged into `1958-1959`
- `1958-1958 Inherit the Wind.pdf` (1958-1958) → merged into `1958-1959`
- `1958-1958 No Time for Sergeants.pdf` (1958-1958) → merged into `1958-1959`
- `1958-1958 Point of No Return.pdf` (1958-1958) → merged into `1958-1959`
- `1958-1958 The Happiest Millionaire.pdf` (1958-1958) → merged into `1958-1959`
- `1958-1958 The Red Mill.pdf` (1958-1958) → merged into `1958-1959`
- `1958-1958 The Remarkable Mr. Pennypacker.pdf` (1958-1958) → merged into `1958-1959`
- `1958-1958 Visit To A Small Planet.pdf` (1958-1958) → merged into `1958-1959`
- `1963-1934 A Far Country.pdf` (1963-1934) → merged into `1963-1964`
- `1982-1893 APPLAUSE.pdf` (1982-1893) → merged into `1982-1983`
- `1985-1886 Lion in Winter.pdf` (1985-1886) → merged into `1985-1986`

## Single-year filenames

Server filenames with only one year. Assumed `YYYY` → season `YYYY-YYYY+1`.

- `1925 The Taming of the Shrew.pdf` → assumed `1925-1926`
- `1929 Androcles and The Lion.pdf` → assumed `1929-1930`
- `1929 SUN-UP.pdf` → assumed `1929-1930`
- `1934 THE STREETS OF NEW YORK.pdf` → assumed `1934-1935`
- `1936 East Lynne.pdf` → assumed `1936-1937`
- `1937 Three One-Act Plays.pdf` → assumed `1937-1938`
- `1938 A Susan Glaspell Evening.pdf` → assumed `1938-1939`
- `1938 The Devil of Pei-Ling.pdf` → assumed `1938-1939`

## Cross-season duplicate detection (adjacent seasons only)

Same normalized title appears in two consecutive seasons. Could be a legitimate revival or a misfiled program.

- `1937-1938` vs `1938-1939`: "THE DEVIL OF PEI-LING" / "The Devil of Pei Ling"
- `1964-1965` vs `1965-1966`: "A Shot in the Dark" / "A SHOT IN THE DARK"
- `1964-1965` vs `1965-1966`: "DAUGHTER OF SILENCE" / "DAUGHTER OF SILENCE"
- `1964-1965` vs `1965-1966`: "Dear Charles" / "DEAR CHARLES"
- `1964-1965` vs `1965-1966`: "Love From a Stranger" / "LOVE FROM A STRANGER"
- `1964-1965` vs `1965-1966`: "Mary, Mary" / "MARY, MARY"
- `1964-1965` vs `1965-1966`: "THE MUSIC MAN" / "THE MUSIC MAN"
- `2001-2002` vs `2002-2003`: "The Complete Works Of William Shakespeare, Abridged (Special)" / "The Complete Works Of William Shakespeare, Abridged (Special)"
- `2002-2003` vs `2003-2004`: "The Complete Works Of William Shakespeare, Abridged (Special)" / "The Complete Works Of William Shakespeare, Abridged (Special)"

## DB shows with no season term

_None — all DB shows have a season term._

## DB shows assigned to multiple season terms

Single Show post is tagged with more than one `tlt_season` term. The script picked the term that matches `show_open_date`; flag for cleanup.

- ID 1021: THE PLAY THAT GOES WRONG — terms: 2023-2024, 2026-2027
- ID 1082: A chorus line — terms: 2019-2020, 2021-2022

## DB shows whose `show_open_date` contradicts the assigned season

_None found._

## Likely duplicate titles within a single season

Same case-folded title appeared from multiple sources; the highest-priority source was kept in `SEASON_LIST.md`.

- `2019-2020`: "Calendar girls" *[DB]*  /  "Calendar Girls" *[program PDF]*
- `2019-2020`: "Evil dead" *[DB]*  /  "Evil Dead" *[program PDF]*
- `2017-2018`: "Blithe spirit" *[DB]*  /  "Blithe Spirit" *[program PDF]*
- `2016-2017`: "Exit laughing" *[DB]*  /  "Exit Laughing" *[program PDF]*
- `2015-2016`: "Rabbit hole" *[DB]*  /  "Rabbit Hole" *[program PDF]*
- `2015-2016`: "A christmas story" *[DB]*  /  "A Christmas Story" *[program PDF]*
- `2014-2015`: "A Midsummer Nights Dream" *[DB]*  /  "A MIDSUMMER NIGHT'S DREAM" *[program PDF]*
- `2014-2015`: "Dial M for murder" *[DB]*  /  "DIAL 'M' FOR MURDER" *[program PDF]*
- `2014-2015`: "Picasso at the lapin agile" *[DB]*  /  "Picasso at the LAPIN AGILE" *[program PDF]*
- `2014-2015`: "Cabaret" *[DB]*  /  "CABARET" *[program PDF]*  /  "Cabaret" *[program PDF]*
- `2013-2014`: "Moonlight and magnolias" *[DB]*  /  "Moonlight and Magnolias" *[program PDF]*
- `2013-2014`: "Chapter two" *[DB]*  /  "chapter two" *[program PDF]*  /  "Chapter Two" *[program PDF]*
- `2013-2014`: "To kill a mockingbird" *[DB]*  /  "TO KILL A MOCKINGBIRD" *[program PDF]*
- `2013-2014`: "Its a wonderful life" *[DB]*  /  "It's a Wonderful Life" *[program PDF]*
- `2009-2010`: "NOISES OFF" *[program PDF]*  /  "Noises Off" *[decade post]*
- `2007-2008`: "Cat On A Hot Tin Roof" *[program PDF]*  /  "Cat on a Hot Tin Roof" *[decade post]*
- `2006-2007`: "DREAMGIRLS" *[program PDF]*  /  "Dreamgirls" *[decade post]*
- `2006-2007`: "THE LION, THE WITCH AND THE WARDROBE" *[program PDF]*  /  "The Lion, The Witch, And The Wardrobe" *[decade post]*
- `2005-2006`: "Ain't Misbehavin'" *[program PDF]*  /  "Ain't Misbehavin" *[decade post]*
- `2005-2006`: "CHARLEY'S AUNT" *[program PDF]*  /  "Charley's Aunt" *[decade post]*
- `2004-2005`: "ANNIE GET YOUR GUN" *[program PDF]*  /  "Annie Get Your Gun" *[decade post]*
- `2002-2003`: "BILOXI BLUES" *[program PDF]*  /  "Biloxi Blues" *[decade post]*
- `2002-2003`: "Inherit the Wind" *[program PDF]*  /  "Inherit The Wind" *[decade post]*
- `2001-2002`: "SOUTH PACIFIC" *[program PDF]*  /  "South Pacific" *[decade post]*
- `2001-2002`: "THE PIANO LESSON" *[program PDF]*  /  "The Piano Lesson" *[decade post]*
- `2000-2001`: "Pump Boys and Dinettes" *[program PDF]*  /  "Pump Boys And Dinettes" *[decade post]*
- `2000-2001`: "The Musical Comedy Murders of 1940" *[program PDF]*  /  "The Musical Comedy Murders Of 1940" *[decade post]*
- `1999-2000`: "Bubbling BROWN SUGAR" *[program PDF]*  /  "BUBBLING BROWN SUGAR" *[decade post]*
- `1999-2000`: "Peter Pan" *[program PDF]*  /  "PETER PAN" *[decade post]*
- `1999-2000`: "the DIARY of Anne Frank" *[program PDF]*  /  "THE DIARY OF ANNE FRANK" *[decade post]*
- `1998-1999`: "Lost in Yonkers" *[program PDF]*  /  "LOST IN YONKERS" *[decade post]*
- `1998-1999`: "Steel Magnolias" *[program PDF]*  /  "STEEL MAGNOLIAS" *[decade post]*
- `1998-1999`: "The Pirates of Penzance" *[program PDF]*  /  "THE PIRATES OF PENZANCE" *[decade post]*
- `1998-1999`: "The Sound of Music" *[program PDF]*  /  "THE SOUND OF MUSIC" *[decade post]*
- `1997-1998`: "A Little Night Music" *[program PDF]*  /  "A LITTLE NIGHT MUSIC" *[decade post]*
- `1997-1998`: "Little Shop of Horrors" *[program PDF]*  /  "LITTLE SHOP OF HORRORS" *[decade post]*
- `1997-1998`: "The Amen Corner" *[program PDF]*  /  "THE AMEN CORNER" *[decade post]*
- `1997-1998`: "The Rainmaker" *[program PDF]*  /  "THE RAINMAKER" *[decade post]*
- `1997-1998`: "The Servant of Two Masters" *[program PDF]*  /  "THE SERVANT OF TWO MASTERS" *[decade post]*
- `1996-1997`: "A Thousand Clowns" *[program PDF]*  /  "A THOUSAND CLOWNS" *[decade post]*
- `1996-1997`: "Noises Off" *[program PDF]*  /  "NOISES OFF" *[decade post]*
- `1996-1997`: "Of Thee I Sing" *[program PDF]*  /  "OF THEE I SING" *[decade post]*
- `1996-1997`: "Once Upon a Mattress" *[program PDF]*  /  "ONCE UPON A MATTRESS" *[decade post]*
- `1995-1996`: "Lend Me A Tenor" *[program PDF]*  /  "LEND ME A TENOR" *[decade post]*
- `1995-1996`: "Man of La Mancha" *[program PDF]*  /  "MAN OF LA MANCHA" *[decade post]*
- `1995-1996`: "Private Lives" *[program PDF]*  /  "PRIVATE LIVES" *[decade post]*
- `1994-1995`: "Joined at the Head" *[program PDF]*  /  "JOINED AT THE HEAD" *[decade post]*
- `1994-1995`: "Kiss Me Kate" *[program PDF]*  /  "KISS ME KATE" *[decade post]*
- `1994-1995`: "Pippin" *[program PDF]*  /  "PIPPIN" *[decade post]*
- `1994-1995`: "The Glass Menagerie" *[program PDF]*  /  "THE GLASS MENAGERIE" *[decade post]*
- `1994-1995`: "To Kill a Mockingbird" *[program PDF]*  /  "TO KILL A MOCKINGBIRD" *[decade post]*
- `1993-1994`: "Ain't Misbehavin'" *[program PDF]*  /  "AIN'T MISBEHAVIN'" *[decade post]*
- `1993-1994`: "Eleemosynary" *[program PDF]*  /  "ELEEMOSYNARY" *[decade post]*
- `1993-1994`: "The 1940's Radio Hour" *[program PDF]*  /  "THE 1940'S RADIO HOUR" *[decade post]*
- `1993-1994`: "The Good Times Are Killing Me" *[program PDF]*  /  "THE GOOD TIMES ARE KILLING ME" *[decade post]*
- `1993-1994`: "The Importance of Being Earnest" *[program PDF]*  /  "THE IMPORTANCE OF BEING EARNEST" *[decade post]*
- `1993-1994`: "You Can't Take it With You" *[program PDF]*  /  "YOU CAN'T TAKE IT WITH YOU" *[decade post]*
- `1992-1993`: "Daddy's Dyin' (Who's Got the Will)" *[program PDF]*  /  "DADDY'S DYI'N (WHO'S GOT THE WILL?)" *[decade post]*
- `1992-1993`: "Driving Miss Daisy" *[program PDF]*  /  "DRIVING MISS DAISY" *[decade post]*
- `1992-1993`: "Quilters" *[program PDF]*  /  "QUILTERS" *[decade post]*
- `1992-1993`: "The Boys in Autumn" *[program PDF]*  /  "THE BOYS IN AUTUMN" *[decade post]*
- `1992-1993`: "The Crucible" *[program PDF]*  /  "THE CRUCIBLE" *[decade post]*
- `1991-1992`: "Agnes of God" *[program PDF]*  /  "AGNES OF GOD" *[decade post]*
- `1991-1992`: "Bullshot Crummond" *[program PDF]*  /  "BULLSHOT CRUMMOND" *[decade post]*
- `1991-1992`: "The Baker's Wife" *[program PDF]*  /  "THE BAKER'S WIFE" *[decade post]*
- `1991-1992`: "The Diviners" *[program PDF]*  /  "THE DIVINERS" *[decade post]*
- `1991-1992`: "The Wiz" *[program PDF]*  /  "THE WIZ" *[decade post]*
- `1990-1991`: "All My Sons" *[program PDF]*  /  "ALL MY SONS" *[decade post]*
- `1990-1991`: "Camelot" *[program PDF]*  /  "CAMELOT" *[decade post]*
- `1990-1991`: "Shivaree" *[program PDF]*  /  "SHIVAREE" *[decade post]*
- `1989-1990`: "ELIZA and THE LUMBERJACK" *[program PDF]*  /  "ELIZA AND THE LUMBERJACK" *[decade post]*
- `1989-1990`: "MASTER HAROLD and the boys" *[program PDF]*  /  "MASTER HAROLD AND THE BOYS" *[decade post]*
- `1989-1990`: "Pass the Butler" *[program PDF]*  /  "PASS THE BUTLER" *[decade post]*
- `1989-1990`: "See How They Run" *[program PDF]*  /  "SEE HOW THEY RUN" *[decade post]*
- `1989-1990`: "Semper Fi" *[program PDF]*  /  "SEMPER FI" *[decade post]*
- `1988-1989`: "Crimes of the Heart" *[program PDF]*  /  "CRIMES OF THE HEART" *[decade post]*
- `1988-1989`: "Whodunnit" *[program PDF]*  /  "WHODUNNIT" *[decade post]*
- `1986-1987`: "Episode 26" *[program PDF]*  /  "EPISODE 26" *[decade post]*
- `1986-1987`: "Sly Fox" *[program PDF]*  /  "SLY FOX" *[decade post]*
- `1986-1987`: "Something's Afoot" *[program PDF]*  /  "SOMETHING'S AFOOT" *[decade post]*
- `1986-1987`: "Spoon River Anthology" *[program PDF]*  /  "SPOON RIVER ANTHOLOGY" *[decade post]*
- `1986-1987`: "To Gillian, On Her 37th Birthday" *[program PDF]*  /  "TO GILLIAN ON HER 37TH BIRTHDAY" *[decade post]*
- `1985-1986`: "Habeas Corpus" *[program PDF]*  /  "HABEAS CORPUS" *[decade post]*
- `1985-1986`: "Murder by the Book" *[program PDF]*  /  "MURDER BY THE BOOK" *[decade post]*
- `1985-1986`: "The Fantasticks" *[program PDF]*  /  "THE FANTASTICKS" *[decade post]*
- `1984-1985`: "JENNY the mail order bride" *[program PDF]*  /  "JENNY, THE MAIL ORDER BRIDE" *[decade post]*
- `1983-1984`: "Here Lies Jeremy Troy" *[program PDF]*  /  "HERE LIES JEREMY TROY" *[decade post]*
- `1983-1984`: "Sweeney Todd" *[program PDF]*  /  "SWEENEY TODD" *[decade post]*
- `1982-1983`: "Murder Among Friends" *[program PDF]*  /  "MURDER AMONG FRIENDS" *[decade post]*
- `1982-1983`: "on golden pond" *[program PDF]*  /  "ON GOLDEN POND" *[decade post]*
- `1982-1983`: "The Glass Menagerie" *[program PDF]*  /  "THE GLASS MENAGERIE" *[decade post]*
- `1981-1982`: "Death of a Salesman" *[program PDF]*  /  "DEATH OF A SALESMAN" *[decade post]*
- `1981-1982`: "The Royal Family" *[program PDF]*  /  "THE ROYAL FAMILY" *[decade post]*
- `1980-1981`: "Heaven Can Wait" *[program PDF]*  /  "HEAVEN CAN WAIT" *[decade post]*
- `1980-1981`: "Kiss Me Kate" *[program PDF]*  /  "KISS ME KATE" *[decade post]*
- `1980-1981`: "Royal Gambit" *[program PDF]*  /  "ROYAL GAMBIT" *[decade post]*
- `1980-1981`: "The Bad Seed" *[program PDF]*  /  "THE BAD SEED" *[decade post]*
- `1979-1980`: "DIAL M FOR MURDER" *[program PDF]*  /  "DIAL "M"FOR MURDER" *[decade post]*
- `1979-1980`: "MARY,MARY" *[program PDF]*  /  "MARY, MARY" *[decade post]*
- `1979-1980`: "THE EFFECT OF GAMMA RAYS ON MAN IN THE MOON MARIGOLDS" *[program PDF]*  /  "THE EFFECT OF GAMMA RAYS ON MAN-IN-THE-MOON MARIGOLDS" *[decade post]*
- `1978-1979`: "The Dark at the Top of the Stairs" *[program PDF]*  /  "THE DARK AT THE TOP OF THE STAIRS" *[decade post]*
- `1978-1979`: "Wait Until Dark" *[program PDF]*  /  "WAIT UNTIL DARK" *[decade post]*
- `1977-1978`: "6 Rms Riv Vu" *[program PDF]*  /  "6 RMS RIV VU" *[decade post]*
- `1977-1978`: "H. M. S. PINAFORE" *[program PDF]*  /  "H.M.S. PINAFORE" *[decade post]*
- `1977-1978`: "No Sex Please, We're British" *[program PDF]*  /  "NO SEX PLEASE, WE'RE BRITISH" *[decade post]*
- `1977-1978`: "The Sunshine Boys" *[program PDF]*  /  "THE SUNSHINE BOYS" *[decade post]*
- `1976-1977`: "Finishing Touches" *[program PDF]*  /  "FINISHING TOUCHES" *[decade post]*
- `1976-1977`: "See How They Run" *[program PDF]*  /  "SEE HOW THEY RUN" *[decade post]*
- `1976-1977`: "Sleuth" *[program PDF]*  /  "SLEUTH" *[decade post]*
- `1976-1977`: "Spoon River Anthology" *[program PDF]*  /  "SPOON RIVER ANTHOLOGY" *[decade post]*
- `1975-1976`: "Cat On A Hot Tin Roof" *[program PDF]*  /  "CAT ON A HOT TIN ROOF" *[decade post]*
- `1975-1976`: "George Washington Slept Here" *[program PDF]*  /  "GEORGE WASHINGTON SLEPT HERE" *[decade post]*
- `1975-1976`: "Idiot's Delight" *[program PDF]*  /  "IDIOT'S DELIGHT" *[decade post]*
- `1975-1976`: "Our Hearts Were Young And Gay" *[program PDF]*  /  "OUR HEARTS WERE YOUNG AND GAY" *[decade post]*
- `1974-1975`: "Night Watch" *[program PDF]*  /  "NIGHT WATCH" *[decade post]*
- `1974-1975`: "The Girls In 509" *[program PDF]*  /  "THE GIRLS IN 509" *[decade post]*
- `1973-1974`: "Anastasia" *[program PDF]*  /  "ANASTASIA" *[decade post]*
- `1973-1974`: "Bell, Book and Candle" *[program PDF]*  /  "BELL, BOOK, AND CANDLE" *[decade post]*
- `1973-1974`: "My Fair Lady" *[program PDF]*  /  "MY FAIR LADY" *[decade post]*
- `1973-1974`: "Prescription Murder" *[program PDF]*  /  "PRESCRIPTION: MURDER" *[decade post]*
- `1973-1974`: "The Seven Year Itch" *[program PDF]*  /  "THE SEVEN YEAR ITCH" *[decade post]*
- `1972-1973`: "Beekman Place" *[program PDF]*  /  "BEEKMAN PLACE" *[decade post]*
- `1972-1973`: "Halfway Up The Tree" *[program PDF]*  /  "HALF WAY UP THE TREE" *[decade post]*
- `1972-1973`: "The Girl in the Freudian Slip" *[program PDF]*  /  "THE GIRL IN THE FREUDIAN SLIP" *[decade post]*
- `1972-1973`: "The Mousetrap" *[program PDF]*  /  "THE MOUSETRAP" *[decade post]*
- `1972-1973`: "The Solid Gold Cadillac" *[program PDF]*  /  "THE SOLID GOLD CADILLAC" *[decade post]*
- `1971-1972`: "Goodbye Charlie" *[program PDF]*  /  "GOODBYE CHARLIE" *[decade post]*
- `1971-1972`: "Gypsy" *[program PDF]*  /  "GYPSY" *[decade post]*
- `1971-1972`: "Period Of Adjustment" *[program PDF]*  /  "PERIOD OF ADJUSTMENT" *[decade post]*
- `1971-1972`: "Plaza Suite" *[program PDF]*  /  "PLAZA SUITE" *[decade post]*
- `1971-1972`: "Private Lives" *[program PDF]*  /  "PRIVATE LIVES" *[decade post]*
- `1971-1972`: "The Best Laid Plans" *[program PDF]*  /  "THE BEST LAID PLANS" *[decade post]*
- `1971-1972`: "The Price" *[program PDF]*  /  "THE PRICE" *[decade post]*
- `1970-1971`: "A Case of Libel" *[program PDF]*  /  "A CASE OF LIBEL" *[decade post]*
- `1970-1971`: "Cactus Flower" *[program PDF]*  /  "CACTUS FLOWER" *[decade post]*
- `1970-1971`: "Critic's Choice" *[program PDF]*  /  "CRITIC'S CHOICE" *[decade post]*
- `1970-1971`: "Damn Yankees" *[program PDF]*  /  "DAMN YANKEES" *[decade post]*
- `1970-1971`: "Harvey" *[program PDF]*  /  "HARVEY" *[decade post]*
- `1970-1971`: "Love in E Flat" *[program PDF]*  /  "LOVE IN "E" FLAT" *[decade post]*
- `1970-1971`: "Suds in Your Eye" *[program PDF]*  /  "SUDS IN YOUR EYE" *[decade post]*
- `1969-1970`: "Don't Drink the Water" *[program PDF]*  /  "DON'T DRINK THE WATER" *[decade post]*
- `1969-1970`: "Reluctant Debutante" *[program PDF]*  /  "RELUCTANT DEBUTANTE" *[decade post]*
- `1969-1970`: "The King and I" *[program PDF]*  /  "THE KING AND I" *[decade post]*
- `1969-1970`: "The NIght of the Iguana" *[program PDF]*  /  "THE NIGHT OF THE IGUANA" *[decade post]*
- `1969-1970`: "The Odd Couple" *[program PDF]*  /  "THE ODD COUPLE" *[decade post]*
- `1968-1969`: "Born Yesterday" *[program PDF]*  /  "BORN YESTERDAY" *[decade post]*
- `1968-1969`: "Bus Stop" *[program PDF]*  /  "BUS STOP" *[decade post]*
- `1968-1969`: "Dinner at Eight" *[program PDF]*  /  "DINNER AT EIGHT" *[decade post]*
- `1968-1969`: "Mister Roberts" *[program PDF]*  /  "MISTER ROBERTS" *[decade post]*
- `1968-1969`: "South Pacific" *[program PDF]*  /  "SOUTH PACIFIC" *[decade post]*
- `1968-1969`: "The Letter" *[program PDF]*  /  "THE LETTER" *[decade post]*
- `1968-1969`: "The Man Who Came To Dinner" *[program PDF]*  /  "THE MAN WHO CAME TO DINNER" *[decade post]*
- `1967-1968`: "Any Wednesday" *[program PDF]*  /  "ANY WEDNESDAY" *[decade post]*
- `1966-1967`: "Carnival" *[program PDF]*  /  "CARNIVAL" *[decade post]*
- `1966-1967`: "For Better For Worse" *[program PDF]*  /  "FOR BETTER, FOR WORSE" *[decade post]*
- `1966-1967`: "Morning's At Seven" *[program PDF]*  /  "MORNING'S AT SEVEN" *[decade post]*
- `1966-1967`: "Never Too Late" *[program PDF]*  /  "NEVER TOO LATE" *[decade post]*
- `1966-1967`: "The Absence of a Cello" *[program PDF]*  /  "THE ABSENCE OF A CELLO" *[decade post]*
- `1966-1967`: "The Warm Peninsula" *[program PDF]*  /  "THE WARM PENINSULA" *[decade post]*
- `1965-1966`: "The Rainmaker" *[program PDF]*  /  "THE RAINMAKER" *[decade post]*
- `1964-1965`: "A Thousand Clowns" *[program PDF]*  /  "A THOUSAND CLOWNS" *[decade post]*
- `1964-1965`: "Anything Goes" *[program PDF]*  /  "ANYTHING GOES" *[decade post]*
- `1964-1965`: "Lullaby" *[program PDF]*  /  "LULLABY" *[decade post]*
- `1964-1965`: "Summer and Smoke" *[program PDF]*  /  "SUMMER AND SMOKE" *[decade post]*
- `1964-1965`: "The Best Man" *[program PDF]*  /  "THE BEST MAN" *[decade post]*
- `1964-1965`: "The Sleeping Prince" *[program PDF]*  /  "THE SLEEPING PRINCE" *[decade post]*
- `1963-1964`: "A Far Country" *[program PDF]*  /  "A FAR COUNTRY" *[decade post]*
- `1963-1964`: "Annie Get Your Gun" *[program PDF]*  /  "ANNIE GET YOUR GUN" *[decade post]*
- `1963-1964`: "Breath of Spring" *[program PDF]*  /  "BREATH OF SPRING" *[decade post]*
- `1963-1964`: "Come Blow Your Horn" *[program PDF]*  /  "COME BLOW YOUR HORN" *[decade post]*
- `1963-1964`: "Everybody Loves Opal" *[program PDF]*  /  "EVERYBODY LOVES OPAL" *[decade post]*
- `1963-1964`: "Roman Candle" *[program PDF]*  /  "ROMAN CANDLE" *[decade post]*
- `1963-1964`: "The Heiress" *[program PDF]*  /  "THE HEIRESS" *[decade post]*
- `1962-1963`: "Operation Mad Ball" *[program PDF]*  /  "OPERATION MAD BALL" *[decade post]*
- `1962-1963`: "Send Me No Flowers" *[program PDF]*  /  "SEND ME NO FLOWERS" *[decade post]*
- `1962-1963`: "SHOWBOAT" *[program PDF]*  /  "SHOW BOAT" *[decade post]*
- `1962-1963`: "Strange Bedfellows" *[program PDF]*  /  "STRANGE BEDFELLOWS" *[decade post]*
- `1962-1963`: "The Fifth Season" *[program PDF]*  /  "THE FIFTH SEASON" *[decade post]*
- `1961-1962`: "Blithe Spirit" *[program PDF]*  /  "BLITHE SPIRIT" *[decade post]*
- `1961-1962`: "Golden Fleecing" *[program PDF]*  /  "GOLDEN FLEECING" *[decade post]*
- `1961-1962`: "Look Homeward, Angel" *[program PDF]*  /  "LOOK HOMEWARD ANGEL" *[decade post]*
- `1961-1962`: "Third Best Sport" *[program PDF]*  /  "THIRD BEST SPORT" *[decade post]*
- `1960-1961`: "Once More With Feeling" *[program PDF]*  /  "ONCE MORE WITH FEELING" *[decade post]*
- `1960-1961`: "South Pacific" *[program PDF]*  /  "SOUTH PACIFIC" *[decade post]*
- `1960-1961`: "The Dark at the Top of the Stairs" *[program PDF]*  /  "THE DARK AT THE TOP OF THE STAIRS" *[decade post]*
- `1960-1961`: "The Pleasure of His Company" *[program PDF]*  /  "THE PLEASURE OF HIS COMPANY" *[decade post]*
- `1960-1961`: "The Women" *[program PDF]*  /  "THE WOMEN" *[decade post]*
- `1959-1960`: "20th Century Follies" *[program PDF]*  /  "20TH CENTURY FOLLIES" *[decade post]*
- `1959-1960`: "Ah, Wilderness" *[program PDF]*  /  "AH WILDERNESS!" *[decade post]*
- `1959-1960`: "Bell, Book and Candle" *[program PDF]*  /  "BELL, BOOK, AND CANDLE" *[decade post]*
- `1959-1960`: "Oklahoma" *[program PDF]*  /  "OKLAHOMA" *[decade post]*
- `1959-1960`: "Present Laughter" *[program PDF]*  /  "PRESENT LAUGHTER" *[decade post]*
- `1958-1959`: "Inherit the Wind" *[program PDF]*  /  "INHERIT THE WIND" *[decade post]*
- `1958-1959`: "No Time for Sergeants" *[program PDF]*  /  "NO TIME FOR SERGEANTS" *[decade post]*
- `1958-1959`: "Point of No Return" *[program PDF]*  /  "POINT OF NO RETURN" *[decade post]*
- `1958-1959`: "The Happiest Millionaire" *[program PDF]*  /  "THE HAPPIEST MILLIONAIRE" *[decade post]*
- `1958-1959`: "The Red Mill" *[program PDF]*  /  "THE RED MILL" *[decade post]*
- `1958-1959`: "The Remarkable Mr. Pennypacker" *[program PDF]*  /  "THE REMARKABLE MR. PENNYPACKER" *[decade post]*
- `1958-1959`: "Visit To A Small Planet" *[program PDF]*  /  "VISIT TO A SMALL PLANET" *[decade post]*
- `1957-1958`: "Bus Stop" *[program PDF]*  /  "BUS STOP" *[decade post]*
- `1957-1958`: "The Desert Song" *[program PDF]*  /  "THE DESERT SONG" *[decade post]*
- `1957-1958`: "The Desk Set" *[program PDF]*  /  "THE DESK SET" *[decade post]*
- `1957-1958`: "The Desperate Hours" *[program PDF]*  /  "THE DESPERATE HOURS" *[decade post]*
- `1957-1958`: "The Great Sebastians" *[program PDF]*  /  "THE GREAT SEBASTIANS" *[decade post]*
- `1957-1958`: "The Reluctant Debutante" *[program PDF]*  /  "THE RELUCTANT DEBUTANTE" *[decade post]*
- `1957-1958`: "Witness for the Prosecution" *[program PDF]*  /  "WITNESS FOR THE PROSECUTION" *[decade post]*
- `1956-1957`: "Carousel" *[program PDF]*  /  "CAROUSEL" *[decade post]*
- `1956-1957`: "Dial M for Murder" *[program PDF]*  /  "DIAL "M" FOR MURDER" *[decade post]*
- `1956-1957`: "Teahouse of the August Moon" *[program PDF]*  /  "TEAHOUSE OF THE AUGUST MOON" *[decade post]*
- `1956-1957`: "The Constant Wife" *[program PDF]*  /  "THE CONSTANT WIFE" *[decade post]*
- `1956-1957`: "The Philadelphia Story" *[program PDF]*  /  "THE PHILADELPHIA STORY" *[decade post]*
- `1956-1957`: "The Ponder Heart" *[program PDF]*  /  "THE PONDER HEART" *[decade post]*
- `1956-1957`: "The Tender Trap" *[program PDF]*  /  "THE TENDER TRAP" *[decade post]*
- `1956-1957`: "Twentieth Century" *[program PDF]*  /  "TWENTIETH CENTURY" *[decade post]*
- `1955-1956`: "Irene" *[program PDF]*  /  "IRENE" *[decade post]*
- `1955-1956`: "Kind Sir" *[program PDF]*  /  "KIND SIR" *[decade post]*
- `1955-1956`: "My Three Angels" *[program PDF]*  /  "MY THREE ANGELS" *[decade post]*
- `1955-1956`: "Twin Beds" *[program PDF]*  /  "TWIN BEDS" *[decade post]*
- `1954-1955`: "Born Yesterday" *[program PDF]*  /  "BORN YESTERDAY" *[decade post]*
- `1954-1955`: "Three Men On A Horse" *[program PDF]*  /  "THREE MEN ON A HORSE" *[decade post]*
- `1953-1954`: "Affairs of State" *[program PDF]*  /  "AFFAIRS OF STATE" *[decade post]*
- `1953-1954`: "Dinner at Eight" *[program PDF]*  /  "DINNER AT EIGHT" *[decade post]*
- `1953-1954`: "See How They Run" *[program PDF]*  /  "SEE HOW THEY RUN" *[decade post]*
- `1953-1954`: "The Firefly" *[program PDF]*  /  "THE FIREFLY" *[decade post]*
- `1952-1953`: "Fancy Meeting You Again" *[program PDF]*  /  "FANCY MEETING YOU AGAIN" *[decade post]*
- `1952-1953`: "The Merry Widow" *[program PDF]*  /  "THE MERRY WIDOW" *[decade post]*
- `1952-1953`: "The Shop at Sly Corner" *[program PDF]*  /  "THE SHOP AT SLY CORNER" *[decade post]*
- `1951-1952`: "Goodbye, My Fancy" *[program PDF]*  /  "GOODBYE, MY FANCY" *[decade post]*
- `1951-1952`: "Seventeen" *[program PDF]*  /  "SEVENTEEN" *[decade post]*
- `1951-1952`: "Smilin' Through" *[program PDF]*  /  "SMILIN' THROUGH" *[decade post]*
- `1951-1952`: "Strange Bedfellows" *[program PDF]*  /  "STRANGE BEDFELLOWS" *[decade post]*
- `1951-1952`: "The Silver Whistle" *[program PDF]*  /  "THE SILVER WHISTLE" *[decade post]*
- `1951-1952`: "The Two Mrs. Carrolls" *[program PDF]*  /  "THE TWO MRS. CARROLLS" *[decade post]*
- `1950-1951`: "Belvedere" *[program PDF]*  /  "BELVEDERE" *[decade post]*
- `1949-1950`: "A Date With Judy" *[program PDF]*  /  "A DATE WITH JUDY" *[decade post]*
- `1949-1950`: "Peg O' My Heart" *[program PDF]*  /  "PEG 'O MY HEART" *[decade post]*
- `1948-1949`: "Biography" *[program PDF]*  /  "BIOGRAPHY" *[decade post]*
- `1947-1948`: "The Amazing Dr Clitterhouse" *[program PDF]*  /  "THE AMAZING DR. CLITTERHOUSE" *[decade post]*
- `1947-1948`: "The Women" *[program PDF]*  /  "THE WOMEN" *[decade post]*
- `1946-1947`: "Dear Ruth" *[program PDF]*  /  "DEAR RUTH" *[decade post]*
- `1946-1947`: "The Torch Bearers" *[program PDF]*  /  "THE TORCHBEARERS" *[decade post]*
- `1946-1947`: "What a Life" *[program PDF]*  /  "WHAT A LIFE" *[decade post]*
- `1945-1946`: "Family Portrait" *[program PDF]*  /  "FAMILY PORTRAIT" *[decade post]*
- `1944-1945`: "Junior Miss" *[program PDF]*  /  "JUNIOR MISS" *[decade post]*
- `1944-1945`: "Kiss and Tell" *[program PDF]*  /  "KISS AND TELL" *[decade post]*
- `1944-1945`: "Only an Orphan Girl" *[program PDF]*  /  "ONLY AN ORPHAN GIRL" *[decade post]*
- `1944-1945`: "The Philadelphia Story" *[program PDF]*  /  "THE PHILADELPHIA STORY" *[decade post]*
- `1944-1945`: "Village Green" *[program PDF]*  /  "VILLAGE GREEN" *[decade post]*
- `1942-1943`: "Arsenic and Old Lace" *[program PDF]*  /  "ARSENIC AND OLD LACE" *[decade post]*
- `1942-1943`: "Juno and the Paycock" *[program PDF]*  /  "JUNO AND THE PAYCOCK" *[decade post]*
- `1942-1943`: "Ladies in Retirement" *[program PDF]*  /  "LADIES IN RETIREMENT" *[decade post]*
- `1942-1943`: "The Man Who Came to Dinner" *[program PDF]*  /  "THE MAN WHO CAME TO DINNER" *[decade post]*
- `1940-1941`: "Tovarich" *[program PDF]*  /  "TOVARICH" *[decade post]*
- `1940-1941`: "Die Fledermaus" *[program PDF]*  /  "DIE FLEDERMAUS" *[decade post]*
- `1940-1941`: "Ethan Frome" *[program PDF]*  /  "ETHAN FROME" *[decade post]*
- `1940-1941`: "Knickerbocker Holiday" *[program PDF]*  /  "KNICKERBOCKER HOLIDAY" *[decade post]*
- `1939-1940`: "Ah, Wilderness" *[program PDF]*  /  "AH! WILDERNESS" *[decade post]*
- `1936-1937`: "East Lynne" *[program PDF]*  /  "EAST LYNNE" *[decade post]*

## Season counts per source

| Season | DB | PDF | Decade post | Unique total |
|---|---:|---:|---:|---:|
| 2026-2027 | 7 | 0 | 0 | 7 |
| 2025-2026 | 7 | 0 | 0 | 7 |
| 2024-2025 | 7 | 0 | 0 | 7 |
| 2023-2024 | 6 | 0 | 0 | 6 |
| 2022-2023 | 7 | 0 | 0 | 7 |
| 2021-2022 | 7 | 0 | 0 | 7 |
| 2019-2020 | 6 | 6 | 0 | 9 |
| 2018-2019 | 8 | 7 | 0 | 12 |
| 2017-2018 | 7 | 7 | 0 | 10 |
| 2016-2017 | 7 | 7 | 0 | 10 |
| 2015-2016 | 7 | 7 | 0 | 10 |
| 2014-2015 | 7 | 13 | 0 | 15 |
| 2013-2014 | 8 | 13 | 0 | 14 |
| 2012-2013 | 0 | 7 | 0 | 7 |
| 2011-2012 | 0 | 5 | 0 | 5 |
| 2010-2011 | 0 | 8 | 0 | 8 |
| 2009-2010 | 0 | 11 | 6 | 15 |
| 2008-2009 | 0 | 2 | 6 | 7 |
| 2007-2008 | 0 | 7 | 5 | 8 |
| 2006-2007 | 0 | 7 | 5 | 7 |
| 2005-2006 | 0 | 7 | 5 | 7 |
| 2004-2005 | 0 | 1 | 5 | 5 |
| 2003-2004 | 0 | 1 | 7 | 7 |
| 2002-2003 | 0 | 5 | 6 | 6 |
| 2001-2002 | 0 | 6 | 6 | 7 |
| 2000-2001 | 0 | 5 | 5 | 6 |
| 1999-2000 | 0 | 3 | 5 | 5 |
| 1998-1999 | 0 | 6 | 5 | 6 |
| 1997-1998 | 0 | 5 | 6 | 6 |
| 1996-1997 | 0 | 5 | 7 | 8 |
| 1995-1996 | 0 | 3 | 7 | 7 |
| 1994-1995 | 0 | 6 | 6 | 7 |
| 1993-1994 | 0 | 7 | 6 | 7 |
| 1992-1993 | 0 | 6 | 6 | 6 |
| 1991-1992 | 0 | 6 | 6 | 7 |
| 1990-1991 | 0 | 6 | 6 | 7 |
| 1989-1990 | 0 | 6 | 7 | 7 |
| 1988-1989 | 0 | 6 | 6 | 7 |
| 1987-1988 | 0 | 6 | 6 | 7 |
| 1986-1987 | 0 | 7 | 7 | 8 |
| 1985-1986 | 0 | 8 | 8 | 9 |
| 1984-1985 | 0 | 5 | 6 | 7 |
| 1983-1984 | 0 | 5 | 6 | 7 |
| 1982-1983 | 0 | 5 | 6 | 6 |
| 1981-1982 | 0 | 6 | 6 | 6 |
| 1980-1981 | 0 | 8 | 8 | 10 |
| 1979-1980 | 0 | 7 | 6 | 7 |
| 1978-1979 | 0 | 6 | 6 | 8 |
| 1977-1978 | 0 | 7 | 6 | 7 |
| 1976-1977 | 0 | 7 | 7 | 9 |
| 1975-1976 | 0 | 7 | 7 | 7 |
| 1974-1975 | 0 | 7 | 7 | 8 |
| 1973-1974 | 0 | 7 | 7 | 7 |
| 1972-1973 | 0 | 7 | 7 | 8 |
| 1971-1972 | 0 | 7 | 7 | 7 |
| 1970-1971 | 0 | 7 | 7 | 7 |
| 1969-1970 | 0 | 7 | 7 | 9 |
| 1968-1969 | 0 | 7 | 7 | 7 |
| 1967-1968 | 0 | 7 | 7 | 8 |
| 1966-1967 | 0 | 7 | 7 | 7 |
| 1965-1966 | 0 | 1 | 7 | 7 |
| 1964-1965 | 0 | 13 | 7 | 13 |
| 1963-1964 | 0 | 7 | 7 | 7 |
| 1962-1963 | 0 | 7 | 7 | 7 |
| 1961-1962 | 0 | 7 | 7 | 8 |
| 1960-1961 | 0 | 7 | 7 | 7 |
| 1959-1960 | 0 | 7 | 7 | 8 |
| 1958-1959 | 0 | 8 | 7 | 8 |
| 1957-1958 | 0 | 7 | 7 | 7 |
| 1956-1957 | 0 | 8 | 8 | 8 |
| 1955-1956 | 0 | 7 | 7 | 10 |
| 1954-1955 | 0 | 7 | 7 | 7 |
| 1953-1954 | 0 | 7 | 7 | 7 |
| 1952-1953 | 0 | 8 | 8 | 10 |
| 1951-1952 | 0 | 8 | 8 | 8 |
| 1950-1951 | 0 | 7 | 8 | 9 |
| 1949-1950 | 0 | 8 | 8 | 8 |
| 1948-1949 | 0 | 8 | 8 | 9 |
| 1947-1948 | 0 | 8 | 8 | 9 |
| 1946-1947 | 0 | 8 | 8 | 9 |
| 1945-1946 | 0 | 7 | 6 | 8 |
| 1944-1945 | 0 | 6 | 5 | 6 |
| 1943-1944 | 0 | 5 | 5 | 6 |
| 1942-1943 | 0 | 7 | 7 | 8 |
| 1941-1942 | 0 | 6 | 6 | 8 |
| 1940-1941 | 0 | 8 | 7 | 9 |
| 1939-1940 | 0 | 2 | 7 | 7 |
| 1938-1939 | 0 | 2 | 5 | 7 |
| 1937-1938 | 0 | 1 | 7 | 8 |
| 1936-1937 | 0 | 1 | 4 | 4 |
| 1935-1936 | 0 | 0 | 7 | 7 |
| 1934-1935 | 0 | 1 | 6 | 6 |
| 1933-1934 | 0 | 0 | 7 | 7 |
| 1930-1931 | 0 | 0 | 8 | 8 |
| 1929-1930 | 0 | 2 | 0 | 2 |
| 1927-1928 | 0 | 2 | 0 | 2 |
| 1926-1927 | 0 | 1 | 0 | 1 |
| 1925-1926 | 0 | 1 | 0 | 1 |
