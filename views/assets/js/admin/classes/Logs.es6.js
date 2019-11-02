import AJAX from '../../AJAX.es6'
import Message from './Message.es6.js'

class Logs {
    constructor () {
        this.init()
    }

    init () {
        let self = this
        document.querySelector('#debug_log .loading_spinner').classList.add('hidden') // hide spinner
        document.querySelector('#debug_log').classList.remove('hidden') // show table

        if (document.getElementById('error_log')) {

            document.getElementById('refresh_error_log').onclick = function (e) {
                self.refresh_log()
            }

            document.getElementById('delete_error_log').onclick = function (e) {
                let confirmed = confirm('Are you sure you want to delete all log entries?')
                if (confirmed) {
                    self.delete_log()
                }
            }
        }
    }

    refresh_log () {

        let self = this
        document.querySelector('#debug_log').classList.add('hidden') // hide table
        document.querySelector('#debug_log .loading_spinner').classList.remove('hidden') // show spinner

        AJAX.get(DataSync.api.url + '/log/get').then(function (result) {
            document.querySelector('#debug_log .loading_spinner').classList.add('hidden') // hide spinner
            if (JSON.parse(result.html) !== document.getElementById('error_log').innerHTML) {
                document.getElementById('error_log').innerHTML = JSON.parse(result.html)
                self.init()
            }
        })
    }

    delete_log () {

        let self = this
        document.querySelector('#debug_log').classList.add('hidden') // hide table
        document.querySelector('#debug_log .loading_spinner').classList.remove('hidden') // show spinner

        AJAX.delete(DataSync.api.url + '/log/delete').then(function (response) {
            let result = {}
            result.success = response
            self.refresh_log()
            Message.show_success_message(result, 'Logs')
            self.init()
        })
    }

    // async static get_receiver_logs (connected_sites, data_sync_start_time) {
    //
    //     let requests = []
    //     let body = []
    //     body['datetime'] = data_sync_start_time
    //
    //     for (const site of connected_sites) {
    //             requests.push(fetch(site.url + '/log/fetch_receiver', {
    //                 method: 'POST',
    //                 body: body
    //             }))
    //
    //     }
    //
    //     return requests
    //
    // }


    static process_receiver_logs(receiver_data) {
            console.log(receiver_data)

            let receiver_logs = []
            receiver_data.forEach(single_receiver_data => receiver_logs.push(single_receiver_data.data.logs))

            let logs = new Logs()
            return logs.save(receiver_logs)
                .then(() => {
                    // REFRESH LOGS
                    if (DataSync.options.debug) {
                        let logs = new Logs()
                        logs.refresh_log()
                    }

                    let admin_message = {}
                    admin_message.success = true
                    admin_message.message = 'Receiver logs retrieved and saved to source. Saving receiver synced posts. . .'
                    Message.admin_message(admin_message)
                })
    }


    async save(logs) {
        const response = await fetch(DataSync.api.url + '/log/create', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': DataSync.api.nonce
            },
            body: JSON.stringify(logs)
        })
        return await response.json()
    }

}

export default Logs