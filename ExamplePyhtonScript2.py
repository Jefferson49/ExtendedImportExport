import os
import logging
import requests
from requests.exceptions import HTTPError

def downloadFile(url, params, local_filename):  

    logging.info("Downloading: " + url)    
    
    for param in params:
        logging.debug(param + ': ' + params[param])
        
    headers = requests.utils.default_headers()
    headers.update(
        {
            "User-Agent": "Python script",
        }
    )
      
    try:
        response = requests.get(url, params, stream=True, timeout=60, headers = headers)
        response.raise_for_status()
    except HTTPError as http_err:
        logging.exception(f'HTTP error occurred: {http_err}')
    except Exception as err:
        logging.exception(f'Other error occurred: {err}')
    else:
        logging.info('Successfully downloaded')

    logging.debug("Response ok: " + str(response.ok))
    logging.debug("Response reason: " + str(response.reason))
    logging.debug("Response code: " + str(response.status_code))
    
    if local_filename != '' :
        logging.info("Opening local file: " + local_filename)   
        file = open(local_filename, 'wb')
        logging.info("Writing to local file")
        for chunk in response.iter_content(chunk_size=128):
            file.write(chunk)

        logging.info("Downloaded file saved")   
        logging.info("")


executed_file = os.path.basename(__file__)
log_file = __file__ + '.log'
logging.basicConfig(filename=log_file, encoding='utf-8', filemode='w', level=logging.DEBUG, format='')
    
print("Started: " + executed_file )
logging.info("Started: " + executed_file)
   
local_path = 'c:/temp/gedcom/'
url = 'http://BASE_URL/index.php?route=/webtrees/ExtendedImportExport'

print("Downloading: " + url)
filename = 'export'
params = {
    'tree': 'tree1', 
    'key': 'hYHBiZM9',
    'file': filename,
    'privacy': 'user',  
}
downloadFile(url, params, local_path + filename + '.ged')

print("Done")
logging.info("Done")