import axios, { AxiosInstance } from "axios";
import { SnackbarProgrammatic as Snackbar } from "buefy";
import env from "../../env";
import Locale from "../Locale";
import Loading from "../Loading";

export default class ApiClient {
    private axios: AxiosInstance;
    protected url = "";
    protected rawResponse = false;

    constructor() {
        this.axios = axios.create({
            baseURL: env.APP_URL + "/api",
            withCredentials: true,
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Content-Type": "application/json",
                "Accept-Language": Locale.get(),
                Accept: "application/json"
            }
        });
    }

    private parseGetData(data?: { [index: string]: any }): any {
        if (!data) {
            return;
        }

        Object.keys(data).map(v => {
            if (data[v] === true || data[v] === false) {
                data[v] = data[v] ? 1 : 0;
            }
        });

        if (data.filter) {
            Object.keys(data.filter).forEach((key) => {
                data[`filter[${key}]`] = data.filter[key];
            });
            delete data.filter;
        }

        return data;
    }

    public async request(
        method: any,
        url: string,
        data?: { [index: string]: any },
        headers?: object,
        responseType?: string
    ): Promise<any> {
        Loading.open();
        let request: {};
        request = { method, url, data, headers };
        if (method === "get") {
            data = this.parseGetData(data);
            request = { method, url, params: data, headers };
        }
        if (responseType) {
            request = { ...request, responseType };
        }

        try {
            let response = await this.axios.request(request);
            response = this.rawResponse ? response : response.data;
            Loading.close();
            return Promise.resolve(response);
        } catch (error) {
            Loading.close();

            if (error.response.status === 401) {
                return Promise.reject(this.rawResponse ? error : error.response);
            }

            const type = "is-danger";
            let message = error.response.data.message;
            if (error.response.data.errors) {
                for (const errors in error.response.data.errors) {
                    if (Object.prototype.hasOwnProperty.call(error.response.data.errors, errors)) {
                        const message: string = error.response.data.errors[errors];
                        Snackbar.open({ type, message });
                    }
                }
            } else {
                Snackbar.open({ type, message });
            }

            return Promise.reject(this.rawResponse ? error : error.response);
        }
    }

    public get(
        url: string,
        data?: object,
        responseType?: string
    ): Promise<any> {
        return this.request("get", url, data, undefined, responseType);
    }

    public show(id?: number): Promise<any> {
        return this.get(`${this.url}/${id}`);
    }

    public index(data?: object): Promise<any> {
        return this.get(`${this.url}`, data);
    }

    public post(
        url: string,
        data?: object,
        hasFile: boolean = false
    ): Promise<any> {
        let headers;
        if (hasFile) {
            headers = { "Content-Type": "multipart/form-data" };
        }
        return this.request("post", url, data, headers);
    }

    public store(data: object, hasFile: boolean = false): Promise<any> {
        return this.post(`${this.url}`, data, hasFile);
    }

    public put(
        url: string,
        data: object,
        hasFile: boolean = false
    ): Promise<any> {
        let headers;
        if (hasFile) {
            headers = { "Content-Type": "multipart/form-data" };
        }
        return this.request("put", url, data, headers);
    }

    public bulkUpdate(
        id: number,
        data: object,
        hasFile: boolean = false
    ): Promise<any> {
        return this.put(`${this.url}/${id}`, data, hasFile);
    }

    public patch(
        url: string,
        key: string,
        value: string | number | boolean,
        hasFile: boolean = false
    ): Promise<any> {
        let headers;
        if (hasFile) {
            headers = { "Content-Type": "multipart/form-data" };
        }
        return this.request("patch", url, { [key]: value }, headers);
    }

    public update(
        id: number,
        key: string,
        value: string | number | boolean,
        hasFile: boolean = false
    ): Promise<any> {
        return this.patch(`${this.url}/${id}`, key, value, hasFile);
    }

    public delete(url: string, data?: object): Promise<any> {
        return this.request("delete", url, data);
    }

    public remove(id?: number | string): Promise<any> {
        return this.delete(`${this.url}/${id}`);
    }

    public restore(id?: number): Promise<any> {
        return this.post(`${this.url}/${id}/restore`);
    }
}
