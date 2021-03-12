@ECHO OFF
docker build . -t omeka_s_veb
docker tag omeka_s_veb registry.docker.libis.be/omeka_s_veb
docker push registry.docker.libis.be/omeka_s_veb
ECHO Image built, tagged and pushed succesfully
PAUSE
