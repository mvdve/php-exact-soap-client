# PHP Exact Globe soap client

This module is a simple PHP soap wrapper which implements Exact Globe specific functionality like NTLM authentication. 
It is still in 
development but fully functional for sending or updating data.

## Entity services access

For the entity services to get access to the Exact database, it is mandatory to add the mac address of the client to 
the 'BacoAccessServers' table. To get the mac address of the client, open an Exact Globe client and open the window 
System -> HR & Security. Click on 'new' and then 'standard'. The mac address will be show in the form field.

Now add the mac address within field 'MachineName' and 'MacAddress2'.

## Docker
Update composer by running the command:

    docker-compose run --rm php bash -c "cd /var/www/html && composer update"

## Examples

See the example folder for usage examples.

## API overview

See the full [Exact entity services documentation](https://www.exactsoftware.com/docs/DocView.aspx?DocumentID={709c8dd6-9938-457d-9a56-868683fcfc05}) for all entities and fields.